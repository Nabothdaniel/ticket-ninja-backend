<?php

namespace App\Models;

/**
 * RefreshToken Model
 */
class RefreshToken extends BaseModel
{
    protected $table = 'refresh_tokens';

    /**
     * Store a new refresh token
     */
    public function createToken($userId, $token, $expiresAt)
    {
        return $this->create([
            'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Find a token by its value
     */
    public function findByToken($token)
    {
        return $this->whereFirst('token', $token);
    }

    /**
     * Delete a token (revoke)
     */
    public function revokeToken($token)
    {
        $sql = "DELETE FROM {$this->table} WHERE token = :token";
        return $this->query($sql, ['token' => $token]);
    }

    /**
     * Delete all tokens for a user
     */
    public function revokeAllForUser($userId)
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :userId";
        return $this->query($sql, ['userId' => $userId]);
    }
}
