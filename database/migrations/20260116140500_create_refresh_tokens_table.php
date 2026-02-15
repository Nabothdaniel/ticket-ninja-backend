<?php
use Phinx\Migration\AbstractMigration;

class CreateRefreshTokensTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('refresh_tokens', [
            'id' => false, 
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);
        $table->addColumn('id', 'uuid')
              ->addColumn('user_id', 'uuid')
              ->addColumn('token', 'string', ['limit' => 255])
              ->addColumn('expires_at', 'timestamp')
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['token'], ['unique' => true])
              ->addIndex(['user_id'])
              ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
              ->create();
    }
}
