<?php
/**
 * Section Repository
 * Handles all database operations for sections table
 */

require_once __DIR__ . '/database-DB.php';

class SectionRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all sections ordered by display_order
     */
    public function getAll() {
        $sql = "SELECT * FROM sections ORDER BY display_order ASC, name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get only active and visible sections
     */
    public function getActiveVisible() {
        $sql = "SELECT * FROM sections 
                WHERE active = 1 AND visible = 1 
                ORDER BY display_order ASC, name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Alias for getActiveVisible() - for frontend
     */
    public function getAllVisibleActive() {
        return $this->getActiveVisible();
    }
    
    /**
     * Get section by key (string identifier)
     */
    public function getByKey($key) {
        $sql = "SELECT * FROM sections WHERE `key` = :key LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['key' => $key]);
        return $stmt->fetch();
    }
    
    /**
     * Get section by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM sections WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Create new section
     */
    public function create($data) {
        $sql = "INSERT INTO sections (`key`, name, image, description, display_order, active, visible) 
                VALUES (:key, :name, :image, :description, :display_order, :active, :visible)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'key' => $data['key'],
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'active' => $data['active'] ?? 1,
            'visible' => $data['visible'] ?? 1
        ]);
    }
    
    /**
     * Update section
     */
    public function update($id, $data) {
        $sql = "UPDATE sections 
                SET `key` = :key,
                    name = :name,
                    image = :image,
                    description = :description,
                    display_order = :display_order,
                    active = :active,
                    visible = :visible
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'key' => $data['key'],
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'active' => $data['active'] ?? 1,
            'visible' => $data['visible'] ?? 1
        ]);
    }
    
    /**
     * Delete section
     */
    public function delete($id) {
        $sql = "DELETE FROM sections WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Get section with product count
     */
    public function getAllWithProductCount() {
        $sql = "SELECT s.*, COUNT(p.id) as product_count 
                FROM sections s 
                LEFT JOIN products p ON s.id = p.section_id AND p.visible = 1
                WHERE s.active = 1 AND s.visible = 1
                GROUP BY s.id 
                ORDER BY s.display_order ASC, s.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all sections with product count for admin
     */
    public function getAllWithProductCountAdmin() {
        $sql = "SELECT s.*, COUNT(p.id) as product_count 
                FROM sections s 
                LEFT JOIN products p ON s.id = p.section_id
                GROUP BY s.id 
                ORDER BY s.display_order ASC, s.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Update display order for a single section
     */
    public function updateDisplayOrder($id, $order) {
        $sql = "UPDATE sections SET display_order = :order WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'order' => $order]);
    }
    
    /**
     * Update display order for multiple sections
     */
    public function updateMultipleDisplayOrders($orderData) {
        try {
            $this->db->beginTransaction();
            
            foreach ($orderData as $item) {
                $this->updateDisplayOrder($item['id'], $item['order']);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating section orders: " . $e->getMessage());
            return false;
        }
    }
}
