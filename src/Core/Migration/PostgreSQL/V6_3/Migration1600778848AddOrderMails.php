<?php

// Diagnostic best-effort PostgreSQL variant; see the emitted inventory manifest for unhandled shapes.

declare (strict_types=1);
namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;
/**
 * @internal
 */
#[Package('after-sales')]
class Migration1600778848AddOrderMails extends MigrationStep
{
    use UpdateMailTrait;
    public function getCreationTimestamp(): int
    {
        return 1600778848;
    }
    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();
        $update = new MailUpdate('order_confirmation_mail', $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_confirmation_mail/en-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_confirmation_mail/en-html.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_confirmation_mail/de-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_confirmation_mail/de-html.html.twig'));
        $this->updateMail($update, $connection);
        $update = new MailUpdate('order_transaction.state.cancelled', $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig'));
        $this->updateMail($update, $connection);
        $update = new MailUpdate('order_transaction.state.paid', $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.paid/en-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.paid/en-html.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.paid/de-plain.html.twig'), $filesystem->readFile(dirname(__FILE__, 2) . '/V6_3' . '/../Fixtures/mails/order_transaction.state.paid/de-html.html.twig'));
        $this->updateMail($update, $connection);
    }
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
