<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnforceUniquePaymentsReference extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('payments')) {
            return;
        }

        // If successful and non-successful rows share a reference, keep successful rows.
        $this->execute("
            DELETE p
            FROM payments p
            INNER JOIN (
                SELECT reference
                FROM payments
                GROUP BY reference
                HAVING SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) > 0
            ) refs ON refs.reference = p.reference
            WHERE p.status <> 'success'
        ");

        // For remaining duplicates, keep the newest row per reference.
        $this->execute("
            DELETE p1
            FROM payments p1
            INNER JOIN payments p2
                ON p1.reference = p2.reference
               AND (
                    COALESCE(p1.updated_at, p1.created_at) < COALESCE(p2.updated_at, p2.created_at)
                    OR (
                        COALESCE(p1.updated_at, p1.created_at) = COALESCE(p2.updated_at, p2.created_at)
                        AND p1.created_at < p2.created_at
                    )
                    OR (
                        COALESCE(p1.updated_at, p1.created_at) = COALESCE(p2.updated_at, p2.created_at)
                        AND p1.created_at = p2.created_at
                        AND p1.id < p2.id
                    )
               )
        ");

        $uniqueRefIndex = $this->fetchRow("
            SELECT COUNT(*) AS cnt
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'payments'
              AND non_unique = 0
              AND column_name = 'reference'
        ");

        if ((int)($uniqueRefIndex['cnt'] ?? 0) === 0) {
            $this->execute("ALTER TABLE payments ADD UNIQUE KEY uniq_payments_reference (`reference`)");
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('payments')) {
            return;
        }

        $index = $this->fetchRow("
            SELECT COUNT(*) AS cnt
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'payments'
              AND index_name = 'uniq_payments_reference'
        ");

        if ((int)($index['cnt'] ?? 0) > 0) {
            $this->execute("ALTER TABLE payments DROP INDEX uniq_payments_reference");
        }
    }
}

