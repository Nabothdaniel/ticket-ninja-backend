<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAttendeeDetailsToTable extends AbstractMigration
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
        $table = $this->table('attendees');
        if (!$table->hasColumn('name')) {
            $table->addColumn('name', 'string', ['limit' => 255, 'null' => true, 'after' => 'user_id']);
        }
        if (!$table->hasColumn('email')) {
            $table->addColumn('email', 'string', ['limit' => 255, 'null' => true, 'after' => 'name']);
        }
        if (!$table->hasColumn('phone')) {
            $table->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'after' => 'email']);
        }
        $table->update();
    }
}
