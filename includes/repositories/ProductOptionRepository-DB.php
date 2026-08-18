<?php
/**
 * Product Option Repository
 * Handles all database operations for product_options table (product variants:
 * weight/amount/color, each with its own price).
 */

require_once __DIR__ . '/../db/database-DB.php';

class ProductOptionRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all options for a product, ordered by display_order
     */
    public function getByProductId($productId) {
        $sql = "SELECT * FROM product_options WHERE product_id = :product_id ORDER BY display_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll();
    }

    /**
     * Get options for multiple products in one query (avoids N+1 in listing
     * pages like section.php). Returns [product_id => [options...]], ordered
     * by display_order within each product. Products with no options are
     * simply absent from the returned array.
     */
    public function getByProductIds($productIds) {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT * FROM product_options WHERE product_id IN ($placeholders) ORDER BY product_id ASC, display_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($productIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[$row['product_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * Get counts of options per product, for all products that have at least one
     * Returns [product_id => count]
     */
    public function getCountsGroupedByProduct() {
        $sql = "SELECT product_id, COUNT(*) as count FROM product_options GROUP BY product_id";
        $stmt = $this->db->query($sql);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['product_id']] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * Create new option
     */
    public function create($data) {
        $sql = "INSERT INTO product_options (product_id, label, price_member, price_public, display_order)
                VALUES (:product_id, :label, :price_member, :price_public, :display_order)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'product_id' => $data['product_id'],
            'label' => $data['label'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'display_order' => $data['display_order'] ?? 0
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Update option
     */
    public function update($id, $data) {
        $sql = "UPDATE product_options
                SET label = :label,
                    price_member = :price_member,
                    price_public = :price_public,
                    display_order = :display_order
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'label' => $data['label'],
            'price_member' => $data['price_member'],
            'price_public' => $data['price_public'],
            'display_order' => $data['display_order'] ?? 0
        ]);
    }

    /**
     * Delete option
     */
    public function delete($id) {
        $sql = "DELETE FROM product_options WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Sync a product's full option list in one transaction: updates rows
     * that carry an existing id, inserts rows without one, and deletes any
     * existing row not present in $options (add-a-listing / remove-a-listing).
     * Blank labels are skipped. $options is a list of
     * ['id' => ?, 'label' => string, 'price_member' => float, 'price_public' => float].
     */
    public function syncForProduct($productId, $options) {
        $this->db->beginTransaction();
        try {
            $existingIds = array_column($this->getByProductId($productId), 'id');
            $keepIds = [];

            $order = 0;
            foreach ($options as $opt) {
                $label = trim($opt['label'] ?? '');
                if ($label === '') {
                    continue;
                }

                $data = [
                    'product_id' => $productId,
                    'label' => $label,
                    'price_member' => (float) ($opt['price_member'] ?? 0),
                    'price_public' => (float) ($opt['price_public'] ?? 0),
                    'display_order' => $order++
                ];

                $optionId = !empty($opt['id']) ? (int) $opt['id'] : null;
                if ($optionId && in_array($optionId, $existingIds)) {
                    $this->update($optionId, $data);
                    $keepIds[] = $optionId;
                } else {
                    $keepIds[] = (int) $this->create($data);
                }
            }

            foreach (array_diff($existingIds, $keepIds) as $staleId) {
                $this->delete($staleId);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
