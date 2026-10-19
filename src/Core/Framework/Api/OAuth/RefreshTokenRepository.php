<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * OAuth integrations should rely on {@see RefreshTokenRepositoryInterface} instead of this concrete Shopware class.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshToken();
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        // User identifier is by design null in case of authentication with integration (and no real user)
        $userIdentifier = $refreshTokenEntity->getAccessToken()->getUserIdentifier();

        if ($userIdentifier) {
            $familyId = $refreshTokenEntity instanceof RefreshToken ? $refreshTokenEntity->getFamilyId() : null;

            $this->connection->createQueryBuilder()
                ->insert('refresh_token')
                ->values([
                    'id' => ':id',
                    'user_id' => ':userId',
                    'token_id' => ':tokenId',
                    'family_id' => ':familyId',
                    'issued_at' => ':issuedAt',
                    'expires_at' => ':expiresAt',
                ])
                ->setParameters([
                    'id' => Uuid::randomBytes(),
                    'userId' => Uuid::fromHexToBytes($userIdentifier),
                    'tokenId' => $refreshTokenEntity->getIdentifier(),
                    'familyId' => $familyId ?? Uuid::randomBytes(),
                    'issuedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'expiresAt' => $refreshTokenEntity->getExpiryDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ], ['id' => ParameterType::BINARY, 'userId' => ParameterType::BINARY, 'familyId' => ParameterType::BINARY])
                ->executeStatement();
        }

        $this->cleanUpExpiredRefreshTokens();
    }

    /**
     * {@inheritdoc}
     */
    public function revokeRefreshToken(string $tokenId): void
    {
        $this->connection->createQueryBuilder()
            ->update('refresh_token')
            ->set('revoked_at', ':revokedAt')
            ->where('token_id = :tokenId')
            ->setParameters([
                'tokenId' => $tokenId,
                'revokedAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ])
            ->executeStatement();

        $this->cleanUpExpiredRefreshTokens();
    }

    /**
     * {@inheritdoc}
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $this->cleanUpExpiredRefreshTokens();

        $refreshToken = $this->connection->createQueryBuilder()
            ->select('id', 'family_id', 'revoked_at')
            ->from('refresh_token')
            ->where('token_id = :tokenId')
            ->setParameter('tokenId', $tokenId)
            ->forUpdate()
            ->executeQuery()
            ->fetchAssociative();

        // no token found, token is invalid
        if (!$refreshToken) {
            return true;
        }

        $familyId = $refreshToken['family_id'];
        if ($familyId === null) {
            $familyId = $refreshToken['id'];
            $familyId = Uuid::fromHexToBytes(Uuid::fromBytesToHex($familyId));
            $this->connection->update(
                'refresh_token',
                ['family_id' => $familyId],
                ['token_id' => $tokenId],
                ['family_id' => ParameterType::BINARY],
            );
        } else {
            $familyId = Uuid::fromHexToBytes(Uuid::fromBytesToHex($familyId));
        }

        if ($refreshToken['revoked_at'] === null) {
            return false;
        }

        $this->connection->delete('refresh_token', ['family_id' => $familyId], ['family_id' => ParameterType::BINARY]);

        return true;
    }

    public function getRefreshTokenFamilyId(string $tokenId): ?string
    {
        $familyId = $this->connection->fetchOne(
            'SELECT family_id FROM refresh_token WHERE token_id = :tokenId',
            ['tokenId' => $tokenId],
        );

        return $familyId === false || $familyId === null ? null : Uuid::fromHexToBytes(Uuid::fromBytesToHex($familyId));
    }

    public function revokeRefreshTokensForUser(string $userId): void
    {
        $this->connection->createQueryBuilder()
            ->delete('refresh_token')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId), ParameterType::BINARY)
            ->executeStatement();
    }

    private function cleanUpExpiredRefreshTokens(): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->createQueryBuilder()
            ->delete('refresh_token')
            ->where('expires_at < :now')
            ->setParameter('now', $now)
            ->executeStatement();
    }
}
