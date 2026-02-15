<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('payments', [
            'id' => false, 
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);
        $table->addColumn('id', 'uuid')
              ->addColumn('reference', 'string', ['limit' => 255])
              ->addColumn('event_id', 'uuid')
              ->addColumn('user_id', 'uuid', ['null' => true])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('currency', 'string', ['limit' => 10, 'default' => 'NGN'])
              ->addColumn('status', 'enum', ['values' => ['pending', 'success', 'failed'], 'default' => 'pending'])
              ->addColumn('series_number', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('payload', 'json', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['reference'], ['unique' => true])
              ->addIndex(['event_id'])
              ->addIndex(['user_id'])
              ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
