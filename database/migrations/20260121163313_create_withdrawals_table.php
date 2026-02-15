<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateWithdrawalsTable extends AbstractMigration
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
        $table = $this->table('withdrawals', [
            'id' => false, 
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);
        $table->addColumn('id', 'uuid')
              ->addColumn('user_id', 'uuid')
              ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addColumn('bank_name', 'string', ['limit' => 255])
              ->addColumn('account_number', 'string', ['limit' => 50])
              ->addColumn('account_name', 'string', ['limit' => 255])
              ->addColumn('status', 'enum', ['values' => ['Pending', 'Processing', 'Completed', 'Failed', 'Cancelled'], 'default' => 'Pending'])
              ->addColumn('reason', 'text', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['user_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
