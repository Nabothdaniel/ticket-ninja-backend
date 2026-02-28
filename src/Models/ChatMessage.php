<?php

namespace App\Models;

/**
 * ChatMessage Model
 */
class ChatMessage extends BaseModel
{
    protected $table = 'chat_messages';

    public function getByConversation($conversationId)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE conversation_id = :conversationId
                ORDER BY created_at ASC";
        return $this->query($sql, ['conversationId' => $conversationId]);
    }
}

