<?php
use Phinx\Migration\AbstractMigration;

class AddRoleToUsersTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('users');
        if (!$table->hasColumn('role')) {
            $table->addColumn('role', 'enum', [
                'values' => ['organizer', 'attendee'],
                'default' => 'attendee',
                'after' => 'email',
                'null' => false
            ])->update();
        }
    }
}
