<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRefundsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('refunds', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);

        $table
            ->addColumn('id', 'uuid')
            ->addColumn('payment_id', 'uuid')
            ->addColumn('requested_by', 'uuid')
            ->addColumn('processed_by', 'uuid', ['null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('reason', 'string', ['limit' => 500])
            ->addColumn('status', 'enum', [
                'values' => ['pending', 'approved', 'rejected', 'processing', 'completed', 'failed'],
                'default' => 'pending'
            ])
            ->addColumn('provider', 'string', ['limit' => 50, 'default' => 'paystack'])
            ->addColumn('provider_reference', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('response_payload', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['payment_id'])
            ->addIndex(['requested_by'])
            ->addIndex(['processed_by'])
            ->addForeignKey('payment_id', 'payments', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('requested_by', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('processed_by', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }
}

