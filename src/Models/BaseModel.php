<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use Ramsey\Uuid\Uuid;

/**
 * Base Model Class
 * 
 * Provides common database operations for all models
 */
abstract class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Find a record by ID
     */
    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Find all records
     */
    public function all($limit = null, $offset = 0)
    {
        $query = "SELECT * FROM {$this->table}";
        
        if ($limit !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Find records by criteria
     */
    public function where($column, $value, $operator = '=')
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} {$operator} :value");
        $stmt->execute(['value' => $value]);
        return $stmt->fetchAll();
    }
    
    /**
     * Find first record by criteria
     */
    public function whereFirst($column, $value, $operator = '=')
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} {$operator} :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        return $stmt->fetch();
    }
    
    /**
     * Create a new record
     */
    public function create($data)
    {
        // Generate UUID if not provided
        if (!isset($data['id'])) {
            $data['id'] = Uuid::uuid4()->toString();
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($query);
        $stmt->execute($data);
        
        return $data['id'];
    }
    
    /**
     * Update a record by ID
     */
    public function update($id, $data)
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $query = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a record by ID
     */
    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Count records
     */
    public function count($column = null, $value = null)
    {
        if ($column && $value) {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE {$column} = :value");
            $stmt->execute(['value' => $value]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table}");
            $stmt->execute();
        }
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
    
    /**
     * Execute raw query
     */
    public function query($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>
