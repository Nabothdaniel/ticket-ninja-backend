<?php

namespace App\Models;

/**
 * Event Model
 */
class Event extends BaseModel
{
    protected $table = 'events';
    
    /**
     * Get events by organizer
     */
    public function getByOrganizer($organizerId)
    {
        return $this->where('organizer_id', $organizerId);
    }
    
    /**
     * Get events by category
     */
    public function getByCategory($category)
    {
        return $this->where('category', $category);
    }
    
    /**
     * Get upcoming events
     */
    public function getUpcoming($limit = 10)
    {
        $sql = "SELECT * FROM {$this->table} WHERE date >= CURDATE() ORDER BY date ASC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Search events
     */
    public function search($query)
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE title LIKE :query 
                OR description LIKE :query 
                OR category LIKE :query 
                ORDER BY created_at DESC";
        
        $searchTerm = "%{$query}%";
        return $this->query($sql, ['query' => $searchTerm]);
    }
    
    /**
     * Get event with organizer details
     */
    public function getWithOrganizer($eventId)
    {
        $sql = "SELECT e.*, u.full_name as organizer_name, u.email as organizer_email, u.company_name
                FROM events e
                LEFT JOIN users u ON e.organizer_id = u.id
                WHERE e.id = :eventId";
        
        $result = $this->query($sql, ['eventId' => $eventId]);
        return $result[0] ?? null;
    }
    
    /**
     * Update available tickets
     */
    public function updateAvailableTickets($eventId, $quantity)
    {
        $sql = "UPDATE {$this->table} 
                SET available_tickets = available_tickets - :quantity 
                WHERE id = :eventId AND available_tickets >= :quantity";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'quantity' => $quantity,
            'eventId' => $eventId
        ]);
    }
}
?>
