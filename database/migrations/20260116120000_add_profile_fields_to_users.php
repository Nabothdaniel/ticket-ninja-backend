<?php
use Phinx\Migration\AbstractMigration;

class AddProfileFieldsToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        
        $hasColumns = false;
        
        if (!$table->hasColumn('phone')) {
            $table->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'after' => 'password']);
            $hasColumns = true;
        }
        if (!$table->hasColumn('industry')) {
            $table->addColumn('industry', 'string', ['limit' => 100, 'null' => true, 'after' => 'phone']);
            $hasColumns = true;
        }
        if (!$table->hasColumn('otp')) {
            $table->addColumn('otp', 'string', ['limit' => 6, 'null' => true, 'after' => 'role']);
             $hasColumns = true;
        }
        if (!$table->hasColumn('otp_expires')) {
            $table->addColumn('otp_expires', 'datetime', ['null' => true, 'after' => 'otp']);
             $hasColumns = true;
        }
        if (!$table->hasColumn('is_verified')) {
            $table->addColumn('is_verified', 'boolean', ['default' => 0, 'after' => 'otp_expires']);
             $hasColumns = true;
        }
        
        if ($hasColumns) {
            $table->update();
        }
    }
}
