<?php

namespace App\Models;

/**
 * ChatConversation Model
 */
class ChatConversation extends BaseModel
{
    protected $table = 'chat_conversations';

    public function getWithMeta($id)
    {
        $sql = "SELECT c.*,
                       u.full_name as user_name,
                       u.email as user_email,
                       a.full_name as assigned_agent_name
                FROM {$this->table} c
                LEFT JOIN users u ON u.id = c.user_id
                LEFT JOIN users a ON a.id = c.assigned_agent_id
                WHERE c.id = :id
                LIMIT 1";
        $rows = $this->query($sql, ['id' => $id]);
        return $rows[0] ?? null;
    }

    public function listForStaff($status = null)
    {
        $params = [];
        $where = '';

        if ($status) {
            $where = "WHERE c.status = :status";
            $params['status'] = $status;
        }

        $sql = "SELECT c.*,
                       u.full_name as user_name,
                       u.email as user_email,
                       a.full_name as assigned_agent_name,
                       (
                           SELECT COUNT(*)
                           FROM chat_messages m
                           WHERE m.conversation_id = c.id
                       ) as message_count
                FROM {$this->table} c
                LEFT JOIN users u ON u.id = c.user_id
                LEFT JOIN users a ON a.id = c.assigned_agent_id
                {$where}
                ORDER BY COALESCE(c.last_message_at, c.created_at) DESC";

        return $this->query($sql, $params);
    }
}

