<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddChatConsentFields extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('chat_conversations')) {
            return;
        }

        $table = $this->table('chat_conversations');

        if (!$table->hasColumn('consent_privacy')) {
            $table->addColumn('consent_privacy', 'boolean', ['default' => false, 'after' => 'subject']);
        }
        if (!$table->hasColumn('consent_ai_training')) {
            $table->addColumn('consent_ai_training', 'boolean', ['default' => false, 'after' => 'consent_privacy']);
        }
        if (!$table->hasColumn('consent_at')) {
            $table->addColumn('consent_at', 'timestamp', ['null' => true, 'after' => 'consent_ai_training']);
        }
        if (!$table->hasColumn('privacy_policy_version')) {
            $table->addColumn('privacy_policy_version', 'string', ['limit' => 30, 'null' => true, 'after' => 'consent_at']);
        }

        $table->update();
    }
}

