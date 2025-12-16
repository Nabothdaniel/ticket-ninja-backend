<?php

namespace App\Models;

/**
 * Withdrawal Model
 */
class Withdrawal extends BaseModel
{
    protected $table = 'withdrawals';
    
    /**
     * Get withdrawals by user
     */
    public function getByUser($userId)
    {
        return $this->where('user_id', $userId);
    }
    
    /**
     * Get withdrawals by status
     */
    public function getByStatus($status)
    {
        return $this->where('status', $status);
    }
    
    /**
     * Get pending withdrawals
     */
    public function getPending()
    {
        return $this->getByStatus('Pending');
    }
    
    /**
     * Update withdrawal status
     */
    public function updateStatus($withdrawalId, $status)
    {
        return $this->update($withdrawalId, ['status' => $status]);
    }
    
    /**
     * Get total withdrawn amount by user
     */
    public function getTotalWithdrawn($userId, $status = 'Completed')
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) as total
                FROM {$this->table}
                WHERE user_id = :userId AND status = :status";
        
        $result = $this->query($sql, [
            'userId' => $userId,
            'status' => $status
        ]);
        
        return $result[0]['total'] ?? 0;
    }
}
?>
