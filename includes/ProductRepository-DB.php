<?php
/**
 * Product Repository
 * Handles all database operations for products table
 */

require_once __DIR__ . '/database-DB.php';

class ProductRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all products
     */
    public function getAll() {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                ORDER BY p.id ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get only visible products
     */
    public function getVisible() {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE p.visible = 1 AND p.active = 1
                ORDER BY s.display_order ASC, p.display_order ASC, p.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get products by section ID
     */
    public function getBySectionId($sectionId, $visibleOnly = true) {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE p.section_id = :section_id";
        
        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }
        
        $sql .= " ORDER BY p.display_order ASC, p.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['section_id' => $sectionId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get products by section key
     */
    public function getBySectionKey($sectionKey, $visibleOnly = true) {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE s.key = :section_key";
        
        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }
        
        $sql .= " ORDER BY p.display_order ASC, p.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['section_key' => $sectionKey]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get product by ID
     */
    public function getById($id) {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE p.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get products that are almost out of stock
     */
    public function getAlmostOutOfStock() {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE p.almost_out_of_stock = 1 AND p.visible = 1 AND p.active = 1
                ORDER BY p.display_order ASC, p.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new product
     */
    public function create($data) {
        $sql = "INSERT INTO products 
                (section_id, name, price_member, price_public, image, description, 
                 display_order, active, visible, almost_out_of_stock) 
                VALUES 
                (:section_id, :name, :price_member, :price_public, :image, :description, 
                 :display_order, :active, :visible, :almost_out_of_stock)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'section_id' => $data['section_id'],
            'name' => $data['name'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'image' => $data['image'] ?? null,
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'active' => $data['active'] ?? 1,
            'visible' => $data['visible'] ?? 1,
            'almost_out_of_stock' => $data['almost_out_of_stock'] ?? 0
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update product
     */
    public function update($id, $data) {
        $sql = "UPDATE products 
                SET section_id = :section_id,
                    name = :name,
                    price_member = :price_member,
                    price_public = :price_public,
                    image = :image,
                    description = :description,
                    display_order = :display_order,
                    active = :active,
                    visible = :visible,
                    almost_out_of_stock = :almost_out_of_stock
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'section_id' => $data['section_id'],
            'name' => $data['name'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'image' => $data['image'] ?? null,
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'active' => $data['active'] ?? 1,
            'visible' => $data['visible'] ?? 1,
            'almost_out_of_stock' => $data['almost_out_of_stock'] ?? 0
        ]);
    }
    
    /**
     * Delete product
     */
    public function delete($id) {
        $sql = "DELETE FROM products WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Toggle product visibility
     */
    public function toggleVisibility($id) {
        $sql = "UPDATE products SET visible = NOT visible WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Set product visibility
     */
    public function setVisibility($id, $visible) {
        $sql = "UPDATE products SET visible = :visible WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'visible' => $visible ? 1 : 0
        ]);
    }
    
    /**
     * Get products grouped by section (for frontend compatibility)
     */
    public function getAllGroupedBySection($visibleOnly = true) {
        $products = $visibleOnly ? $this->getVisible() : $this->getAll();
        
        $grouped = [];
        foreach ($products as $product) {
            $sectionKey = $product['section_key'];
            if (!isset($grouped[$sectionKey])) {
                $grouped[$sectionKey] = [];
            }
            $grouped[$sectionKey][] = $product;
        }
        
        return $grouped;
    }
    
    /**
     * Search products by name or description
     */
    public function search($query, $visibleOnly = true) {
        $sql = "SELECT p.*, s.name as section_name, s.key as section_key 
                FROM products p 
                LEFT JOIN sections s ON p.section_id = s.id 
                WHERE (p.name LIKE :query OR p.description LIKE :query)";
        
        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }
        
        $sql .= " ORDER BY p.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }
    
    /**
     * Update display order for a product
     */
    public function updateDisplayOrder($id, $order) {
        $sql = "UPDATE products SET display_order = :display_order WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'display_order' => $order
        ]);
    }
    
    /**
     * Batch update display orders
     */
    public function updateMultipleDisplayOrders($orderData) {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE products SET display_order = :display_order WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            foreach ($orderData as $id => $order) {
                $stmt->execute([
                    'id' => $id,
                    'display_order' => $order
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
