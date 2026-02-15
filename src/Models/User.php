<?php

namespace App\Models;

/**
 * User Model
 */
class User extends BaseModel
{
    protected $table = 'users';
    
    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->whereFirst('email', $email);
    }
    
    /**
     * Create a new user
     */
    public function createUser($data)
    {
        // Hash password before storing
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        return $this->create($data);
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword($email, $password)
    {
        $user = $this->findByEmail($email);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    /**
     * Get user events
     */
    public function getUserEvents($userId)
    {
        $sql = "SELECT * FROM events WHERE organizer_id = :userId ORDER BY created_at DESC";
        return $this->query($sql, ['userId' => $userId]);
    }
    
    /**
     * Get user stats
     */
    public function getUserStats($userId)
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT e.id) as total_events,
                COALESCE(SUM(e.total_tickets - e.available_tickets), 0) as total_tickets_sold,
                COALESCE(SUM((e.total_tickets - e.available_tickets) * e.ticket_price), 0) as gross_revenue,
                COALESCE(SUM((e.total_tickets - e.available_tickets) * e.ticket_price) * 0.03, 0) as platform_fee,
                COALESCE(SUM((e.total_tickets - e.available_tickets) * e.ticket_price) * 0.97, 0) as net_revenue
            FROM events e
            WHERE e.organizer_id = :userId
        ";
        
        $result = $this->query($sql, ['userId' => $userId]);
        return $result[0] ?? [
            'total_events' => 0,
            'total_tickets_sold' => 0,
            'gross_revenue' => 0,
            'platform_fee' => 0,
            'net_revenue' => 0
        ];
    }
}
?>
