<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateChatTables extends AbstractMigration
{
    public function change(): void
    {
        $conversations = $this->table('chat_conversations', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);

        $conversations
            ->addColumn('id', 'uuid')
            ->addColumn('user_id', 'uuid', ['null' => true])
            ->addColumn('guest_token', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('assigned_agent_id', 'uuid', ['null' => true])
            ->addColumn('status', 'enum', [
                'values' => ['open', 'assigned', 'resolved', 'closed'],
                'default' => 'open'
            ])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('last_message_at', 'timestamp', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'])
            ->addIndex(['guest_token'])
            ->addIndex(['assigned_agent_id'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->addForeignKey('assigned_agent_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();

        $messages = $this->table('chat_messages', [
            'id' => false,
            'primary_key' => 'id',
            'collation' => 'utf8mb4_general_ci',
            'charset' => 'utf8mb4'
        ]);

        $messages
            ->addColumn('id', 'uuid')
            ->addColumn('conversation_id', 'uuid')
            ->addColumn('sender_type', 'enum', [
                'values' => ['user', 'bot', 'agent', 'admin'],
                'default' => 'user'
            ])
            ->addColumn('sender_id', 'uuid', ['null' => true])
            ->addColumn('message', 'text')
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['conversation_id'])
            ->addIndex(['sender_type'])
            ->addForeignKey('conversation_id', 'chat_conversations', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('sender_id', 'users', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->create();
    }
}

