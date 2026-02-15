<?php
use Phinx\Migration\AbstractMigration;

class UpdatePreferencesAndSettings extends AbstractMigration
{
    public function change()
    {
        // 1. Update Users Table: Replace industry with interests
        $table = $this->table('users');
        
        if ($table->hasColumn('industry')) {
            $table->removeColumn('industry')
                  ->save();
        }
        
        if (!$table->hasColumn('interests')) {
            $table->addColumn('interests', 'text', ['null' => true, 'after' => 'phone'])
                  ->update();
        }

        // 2. Create Settings Table
        if (!$this->hasTable('settings')) {
            $settings = $this->table('settings', ['id' => false, 'primary_key' => 'key']);
            $settings->addColumn('key', 'string', ['limit' => 50])
                     ->addColumn('value', 'text')
                     ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
                     ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                     ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                     ->create();

            // Seed default settings using execute to avoid double insert issues if table created but empty
            // Using REPLACE INTO to be safe
             $this->execute("INSERT INTO settings (`key`, `value`, `description`) VALUES 
            ('platform_fee_percent', '3.0', 'Percentage fee per ticket sale'),
            ('platform_fee_fixed', '100', 'Fixed fee per ticket sale in local currency')");
        }
    }
}
