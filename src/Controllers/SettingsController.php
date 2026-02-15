<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;

class SettingsController
{
    private $db;
    
    public function __construct()
    {
        $this->db = \App\Core\Database::getInstance();
    }
    
    public function getSettings()
    {
        $authUser = AuthMiddleware::getAuthUser();
        // Strict Admin Check should happen here
        // For now, allow logged in users (or restrict to organizer/admin role)
        
        $stmt = $this->db->query("SELECT * FROM settings");
        $settings = $stmt->fetchAll();
        
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['key']] = $setting['value'];
        }
        
        Response::success($formatted);
    }
    
    public function updateSettings()
    {
        $authUser = AuthMiddleware::getAuthUser();
        // Add admin role check if 'role' column exists and is 'admin'
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('Invalid input');
        }
        
        try {
            $this->db->beginTransaction();
            
            foreach ($input as $key => $value) {
                $stmt = $this->db->prepare("UPDATE settings SET value = :value WHERE `key` = :key");
                $stmt->execute(['value' => $value, 'key' => $key]);
                
                if ($stmt->rowCount() === 0) {
                     // Insert if not exists (upsert logic could be better but this works for known keys)
                     $stmt = $this->db->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (:key, :value)");
                     $stmt->execute(['key' => $key, 'value' => $value]);
                }
            }
            
            $this->db->commit();
            Response::success(null, 'Settings updated successfully');
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }
}
