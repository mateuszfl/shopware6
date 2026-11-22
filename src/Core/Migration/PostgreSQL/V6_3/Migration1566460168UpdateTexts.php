<?php declare(strict_types=1);

namespace Shopware\Core\Migration\PostgreSQL\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Migration\V6_3\PostInitialSchemaPostgreSQLTest
 */
#[Package('framework')]
class Migration1566460168UpdateTexts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1566460168;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $updates = [
            ['Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first, in order to avoid defaults on payment.', 'Payment by invoice. Shopware provides automatic invoicing for all customers on orders after the first. This is to avoid defaults on payment.', 'Invoice'],
            ['Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnung automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.', 'Sie zahlen einfach und bequem auf Rechnung. Shopware bietet z.B. auch die Möglichkeit, Rechnungen automatisiert erst ab der 2. Bestellung für Kunden zur Verfügung zu stellen, um Zahlungsausfälle zu vermeiden.', 'Rechnung'],
            ['Additional text', 'Pre-authorized payment, funds are withdrawn directly from the debited account.', 'Direct Debit'],
            ['Zusatztext', 'Vorab autorisierte Zahlungsvereinbarung, Zahlungen werden direkt vom zu belastenden Konto abgebucht.', 'Lastschrift'],
            ['Pay when you get the order', 'Payment upon receipt of goods.', 'Cash on delivery'],
            ['', 'Zahlung bei Erhalt der Ware.', 'Nachnahme'],
        ];

        foreach ($updates as [$oldDescription, $newDescription, $name]) {
            $connection->update(
                'payment_method_translation',
                ['description' => $newDescription],
                ['description' => $oldDescription, 'name' => $name],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING]
            );
        }
    }
}
