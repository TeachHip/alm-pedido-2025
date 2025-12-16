<?php
/**
 * Cart Repository
 * Handles all database operations for carts and cart_items tables
 */

require_once __DIR__ . '/database-DB.php';

class CartRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate ticket number in format #ALM-YYYY-MM-####
     */
    public function generateTicketNumber() {
        $year = date('Y');
        $month = date('m');
        $prefix = "#ALM-{$year}-{$month}-";
        
        // Get the count of orders this month
        $sql = "SELECT COUNT(*) as count FROM carts 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = :year_month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year_month' => "{$year}-{$month}"]);
        $result = $stmt->fetch();
        
        $nextNumber = ($result['count'] ?? 0) + 1;
        $paddedNumber = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $paddedNumber;
    }
    
    /**
     * Create new cart and cart items
     * Returns array with cart_id and ticket number
     */
    public function createCart($cartItems, $clientId = null, $sessionId = null) {
        try {
            $this->db->beginTransaction();
            
            // Calculate total
            $totalPrice = 0;
            foreach ($cartItems as $item) {
                $totalPrice += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
            }
            
            // Generate ticket number
            $ticketNumber = $this->generateTicketNumber();
            
            // Create cart record
            $sql = "INSERT INTO carts (client_id, session_id, status, total_price, created_at) 
                    VALUES (:client_id, :session_id, 'active', :total_price, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'client_id' => $clientId,
                'session_id' => $sessionId ?? session_id(),
                'total_price' => $totalPrice
            ]);
            
            $cartId = $this->db->lastInsertId();
            
            // Create cart items
            $itemSql = "INSERT INTO cart_items (cart_id, product_id, quantity, price_snapshot, subtotal) 
                        VALUES (:cart_id, :product_id, :quantity, :price_snapshot, :subtotal)";
            $itemStmt = $this->db->prepare($itemSql);
            
            foreach ($cartItems as $item) {
                $quantity = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $subtotal = $quantity * $price;
                
                $itemStmt->execute([
                    'cart_id' => $cartId,
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $quantity,
                    'price_snapshot' => $price,
                    'subtotal' => $subtotal
                ]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'cart_id' => $cartId,
                'ticket' => $ticketNumber,
                'total' => $totalPrice
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating cart: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get cart by ID
     */
    public function getById($cartId) {
        $sql = "SELECT * FROM carts WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $cartId]);
        return $stmt->fetch();
    }
    
    /**
     * Get cart items for a cart
     */
    public function getCartItems($cartId) {
        $sql = "SELECT ci.*, p.name as product_name, p.image 
                FROM cart_items ci 
                LEFT JOIN products p ON ci.product_id = p.id 
                WHERE ci.cart_id = :cart_id 
                ORDER BY ci.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cart_id' => $cartId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all carts (for admin)
     */
    public function getAll($limit = 100) {
        $sql = "SELECT c.*, 
                COUNT(ci.id) as item_count,
                cl.email as client_email
                FROM carts c 
                LEFT JOIN cart_items ci ON c.id = ci.cart_id
                LEFT JOIN clients cl ON c.client_id = cl.id
                GROUP BY c.id
                ORDER BY c.created_at DESC 
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Update cart status
     */
    public function updateStatus($cartId, $status) {
        $sql = "UPDATE carts SET status = :status";
        
        if ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }
        
        $sql .= " WHERE id = :cart_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'cart_id' => $cartId
        ]);
    }
    
    /**
     * Get ticket number by cart ID
     */
    public function getTicketNumber($cartId) {
        $cart = $this->getById($cartId);
        if (!$cart) return null;
        
        $createdAt = new DateTime($cart['created_at']);
        $year = $createdAt->format('Y');
        $month = $createdAt->format('m');
        
        // Count how many carts were created before this one in the same month
        $sql = "SELECT COUNT(*) as position FROM carts 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = :year_month 
                AND id <= :cart_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'year_month' => "{$year}-{$month}",
            'cart_id' => $cartId
        ]);
        $result = $stmt->fetch();
        
        $position = $result['position'] ?? 1;
        $paddedNumber = str_pad($position, 4, '0', STR_PAD_LEFT);
        
        return "#ALM-{$year}-{$month}-{$paddedNumber}";
    }
}
