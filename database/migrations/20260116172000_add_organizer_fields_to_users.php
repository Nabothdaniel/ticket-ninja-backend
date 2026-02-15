<?php
use Phinx\Migration\AbstractMigration;

class AddOrganizerFieldsToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        
        if (!$table->hasColumn('company_name')) {
            $table->addColumn('company_name', 'string', ['limit' => 255, 'null' => true, 'after' => 'full_name']);
        }
        
        if (!$table->hasColumn('bio')) {
            $table->addColumn('bio', 'text', ['null' => true, 'after' => 'role']);
        }
        
        $table->update();
    }
}
