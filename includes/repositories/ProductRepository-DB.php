<?php
/**
 * Product Repository
 * Handles all database operations for products table
 */

require_once __DIR__ . '/../db/database-DB.php';

class ProductRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Base "product joined with its section" SELECT, shared by every
     * listing method below -- only the WHERE/ORDER BY differs per caller.
     * Previously this JOIN was retyped verbatim in 7 different methods.
     */
    private function selectWithSection() {
        return "SELECT p.*, s.name as section_name, s.key as section_key
                FROM products p
                LEFT JOIN sections s ON p.section_id = s.id";
    }

    /**
     * Get all non-deprecated products (the normal admin working set).
     * Deprecated (active=0) products are excluded here on purpose -- see
     * getDeprecated() -- so they never resurface in the regular list.
     */
    public function getAll() {
        $sql = $this->selectWithSection() . " WHERE p.active = 1 ORDER BY s.display_order ASC, p.display_order ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get only deprecated ("antiguo") products -- permanently retired, kept
     * solely so historical tickets/orders referencing them still resolve.
     * Never shown in the storefront or the normal admin list; see
     * admin/products-antiguos.php, the only place these surface.
     */
    public function getDeprecated() {
        $sql = $this->selectWithSection() . " WHERE p.active = 0 ORDER BY s.display_order ASC, p.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get only visible products
     */
    public function getVisible() {
        $sql = $this->selectWithSection() . "
                WHERE p.visible = 1 AND p.active = 1
                ORDER BY s.display_order ASC, p.display_order ASC, p.name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get products by section ID
     */
    public function getBySectionId($sectionId, $visibleOnly = true) {
        $sql = $this->selectWithSection() . " WHERE p.section_id = :section_id";

        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }

        $sql .= " ORDER BY p.display_order ASC, p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['section_id' => $sectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Alias for getBySectionId with visible filter - for frontend
     */
    public function getBySectionVisible($sectionId) {
        return $this->getBySectionId($sectionId, true);
    }

    /**
     * Get products by section key
     * Special case: 'fin_stock' returns all products with almost_out_of_stock flag
     */
    public function getBySectionKey($sectionKey, $visibleOnly = true) {
        // Special virtual section for "Fin de stock"
        if ($sectionKey === 'fin_stock') {
            $sql = $this->selectWithSection() . " WHERE p.almost_out_of_stock = 1";

            if ($visibleOnly) {
                $sql .= " AND p.visible = 1 AND p.active = 1";
            }

            $sql .= " ORDER BY p.display_order ASC, p.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // Normal section query
        $sql = $this->selectWithSection() . " WHERE s.key = :section_key";

        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }

        $sql .= " ORDER BY p.display_order ASC, p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['section_key' => $sectionKey]);
        return $stmt->fetchAll();
    }

    /**
     * Check if any of the given product IDs belong to the given section key
     * (used for the Pedido Expres cart fee).
     */
    public function anyInSectionKey($productIds, $sectionKey) {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT COUNT(*) as count
                FROM products p
                JOIN sections s ON p.section_id = s.id
                WHERE p.id IN ($placeholders) AND s.key = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($productIds, [$sectionKey]));
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Get product by ID
     */
    public function getById($id) {
        $sql = $this->selectWithSection() . " WHERE p.id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Get products that are almost out of stock
     */
    public function getAlmostOutOfStock() {
        $sql = $this->selectWithSection() . "
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
                (section_id, name, ticket_name, price_member, price_public, iva_rate, image, description,
                 display_order, active, visible, almost_out_of_stock)
                VALUES
                (:section_id, :name, :ticket_name, :price_member, :price_public, :iva_rate, :image, :description,
                 :display_order, :active, :visible, :almost_out_of_stock)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'section_id' => $data['section_id'],
            'name' => $data['name'],
            'ticket_name' => $data['ticket_name'] ?? $data['name'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'iva_rate' => $data['iva_rate'] ?? '4',
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
                    ticket_name = :ticket_name,
                    price_member = :price_member,
                    price_public = :price_public,
                    iva_rate = :iva_rate,
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
            'ticket_name' => $data['ticket_name'] ?? $data['name'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'iva_rate' => $data['iva_rate'] ?? '4',
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
     * Whether this product has ever been ordered. cart_items.product_id's FK
     * has no ON DELETE clause, so the DB already refuses to delete a product
     * with history -- this lets callers check proactively and show a clean
     * message ("mark it antiguo instead") rather than a raw FK error.
     */
    public function hasOrderHistory($id) {
        $sql = "SELECT COUNT(*) FROM cart_items WHERE product_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Set of product IDs that have ever been ordered, for the admin products
     * list to hide "Eliminar" on those rows without an N+1 query. Mirrors
     * ProductOptionRepository::getCountsGroupedByProduct()'s shape.
     */
    public function getOrderedProductIds() {
        $sql = "SELECT DISTINCT product_id FROM cart_items";
        $stmt = $this->db->query($sql);
        return array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
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
     * Set the deprecated ("antiguo") flag. Independent of visible -- see
     * getDeprecated()/getAll() docblocks. Setting active=0 already hides the
     * product from every storefront query (all of them require
     * visible=1 AND active=1), so this alone is enough to retire a product.
     */
    public function setActive($id, $active) {
        $sql = "UPDATE products SET active = :active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'active' => $active ? 1 : 0
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
        $sql = $this->selectWithSection() . " WHERE (p.name LIKE :query_name OR p.description LIKE :query_description)";

        if ($visibleOnly) {
            $sql .= " AND p.visible = 1 AND p.active = 1";
        }

        $sql .= " ORDER BY p.name ASC";

        $stmt = $this->db->prepare($sql);
        $searchTerm = '%' . $query . '%';
        $stmt->execute(['query_name' => $searchTerm, 'query_description' => $searchTerm]);
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
            error_log("Error updating product orders: " . $e->getMessage());
            return false;
        }
    }
}
