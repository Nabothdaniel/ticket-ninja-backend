<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAgentRoleToUsers extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        $this->execute("ALTER TABLE users MODIFY role ENUM('admin','agent','organizer','attendee') NOT NULL DEFAULT 'attendee'");
    }

    public function down(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        // Revert non-supported roles to attendee before narrowing enum.
        $this->execute("UPDATE users SET role = 'attendee' WHERE role IN ('admin','agent')");
        $this->execute("ALTER TABLE users MODIFY role ENUM('organizer','attendee') NOT NULL DEFAULT 'attendee'");
    }
}

