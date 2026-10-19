<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Contract;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\Authentication\UserCredentialsChangedSubscriber;
use Shopware\Core\Framework\Api\OAuth\AccessToken;
use Shopware\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\OAuth\RefreshToken;
use Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Tests\Integration\Core\Framework\Contract\_fixtures\FrameworkSqlFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class OAuthSqlContractTest extends TestCase
{
    private const USER = '0000000000000000000000000000ff01';

    private FrameworkSqlFixture $fixture;

    private Connection $connection;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->fixture = new FrameworkSqlFixture();
        $this->fixture->setUp();
        $this->connection = $this->fixture->connection;
        $this->clock = new MockClock('2026-10-18 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        $this->fixture->tearDown();
    }

    public function testRefreshTokenExpiryKeepsExactCutoffAndDeletesOnlyOlderRows(): void
    {
        $repository = new RefreshTokenRepository($this->connection, $this->clock);
        foreach (['before' => '-1 second', 'equal' => '+0 seconds', 'after' => '+1 second'] as $name => $offset) {
            $token = new RefreshToken();
            $token->setIdentifier($name);
            $token->setAccessToken(new AccessToken(new ApiClient('contract', writeAccess: true, confidential: false), [], self::USER));
            $token->setExpiryDateTime($this->clock->now()->modify($offset));
            $repository->persistNewRefreshToken($token);
        }
        static::assertTrue($repository->isRefreshTokenRevoked('before'));
        static::assertFalse($repository->isRefreshTokenRevoked('equal'));
        static::assertFalse($repository->isRefreshTokenRevoked('after'));
        $this->clock->sleep(1);
        static::assertTrue($repository->isRefreshTokenRevoked('equal'));
        static::assertFalse($repository->isRefreshTokenRevoked('after'));
        $repository->revokeRefreshTokensForUser(self::USER);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_token'));
    }

    public function testRefreshFamilyBinaryRoundTripAndReplayRevokesWholeFamily(): void
    {
        $repository = new RefreshTokenRepository($this->connection, $this->clock);
        $family = Uuid::fromHexToBytes(self::USER);
        foreach (['first', 'second'] as $name) {
            $token = new RefreshToken();
            $token->setIdentifier($name);
            $token->setFamilyId($family);
            $token->setAccessToken(new AccessToken(new ApiClient('contract', writeAccess: true, confidential: false), [], self::USER));
            $token->setExpiryDateTime($this->clock->now()->modify('+1 hour'));
            $repository->persistNewRefreshToken($token);
        }
        static::assertSame($family, $repository->getRefreshTokenFamilyId('first'));
        $repository->revokeRefreshToken('first');
        static::assertTrue($repository->isRefreshTokenRevoked('first'));
        static::assertTrue($repository->isRefreshTokenRevoked('second'));
        static::assertNull($repository->getRefreshTokenFamilyId('second'));
    }

    public function testAuthCodeExpiryAndBinaryUserRevocation(): void
    {
        $repository = new AuthCodeRepository($this->connection, $this->clock);
        foreach (['before' => '-1 second', 'equal' => '+0 seconds', 'after' => '+1 second'] as $name => $offset) {
            $code = $repository->getNewAuthCode();
            $code->setIdentifier($name);
            $code->setUserIdentifier(self::USER);
            $code->setClient(new ApiClient('contract', writeAccess: true, confidential: false));
            $code->setExpiryDateTime($this->clock->now()->modify($offset));
            $repository->persistNewAuthCode($code);
        }
        static::assertTrue($repository->isAuthCodeRevoked('before'));
        static::assertFalse($repository->isAuthCodeRevoked('equal'));
        static::assertFalse($repository->isAuthCodeRevoked('after'));
        $repository->revokeAuthCodesForUser(self::USER);
        static::assertTrue($repository->isAuthCodeRevoked('after'));
    }

    public function testInactiveAppCannotAuthenticateRegardlessOfScalarRepresentation(): void
    {
        $key = AccessKeyHelper::generateAccessKey('integration');
        static::assertNotSame('', $key);
        $this->fixture->insert('integration', ['id' => self::USER, 'access_key' => $key, 'label' => 'app']);
        $this->fixture->insert('app', ['id' => Uuid::randomHex(), 'name' => 'contract', 'integration_id' => self::USER, 'active' => 0]);
        $repository = new ClientRepository($this->connection, $this->clock, new PublicClientRegistry([]));
        static::assertNull($repository->getClientEntity($key));
        $this->connection->executeStatement('UPDATE app SET active = 1');
        static::assertNotNull($repository->getClientEntity($key));
    }

    public function testDeactivatingUserRevokesTokensAndWritesPasswordTimestamp(): void
    {
        $this->fixture->insert('user', ['id' => self::USER, 'active' => 0]);
        $this->fixture->insert('refresh_token', ['id' => self::USER, 'user_id' => self::USER, 'token_id' => 'refresh', 'family_id' => self::USER, 'expires_at' => '2026-10-18 13:00:00']);
        $this->fixture->insert('oauth_auth_code', ['id' => self::USER, 'user_id' => self::USER, 'code_id' => 'code', 'expires_at' => '2026-10-18 13:00:00']);
        $subscriber = new UserCredentialsChangedSubscriber(new RefreshTokenRepository($this->connection, $this->clock), new AuthCodeRepository($this->connection, $this->clock), $this->connection, $this->clock);
        $subscriber->onUserWritten(new EntityWrittenEvent('user', [new EntityWriteResult(self::USER, ['id' => self::USER, 'active' => false], 'user', EntityWriteResult::OPERATION_UPDATE)], Context::createDefaultContext()));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_token'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM oauth_auth_code'));
        $updated = $this->connection->fetchOne('SELECT last_updated_password_at FROM ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('user'));
        static::assertSame($this->clock->now()->getTimestamp(), (new \DateTimeImmutable($updated))->getTimestamp());
    }

    public function testLicenseChangeClearsUserTokens(): void
    {
        $this->fixture->insert('user', ['id' => self::USER, 'store_token' => 'old']);
        $config = new StaticSystemConfigService(['core.store.shopSecret' => 'secret', 'core.store.licenseHost' => 'old']);
        $subscriber = new LicenseHostChangedSubscriber($config, $this->connection);
        $subscriber->onLicenseHostChanged(new BeforeSystemConfigChangedEvent('core.store.licenseHost', 'new', null));
        static::assertNull($this->connection->fetchOne('SELECT store_token FROM ' . $this->connection->getDatabasePlatform()->quoteSingleIdentifier('user')));
        static::assertNull($config->get('core.store.shopSecret'));
    }
}
