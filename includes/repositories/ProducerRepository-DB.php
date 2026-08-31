<?php
/**
 * Producer Repository
 * Handles all database operations for the producers table.
 *
 * Managed via admin/producers.php (a lean CRUD: name + active only, no
 * contact fields). The product edit form's "Productor" field is a closed
 * dropdown (admin/edit-product.php) -- add a new producer on
 * admin/producers.php first, then assign it to a product.
 */

require_once __DIR__ . '/../db/database-DB.php';

class ProducerRepository {
    const PLACEHOLDER_NAME = 'Sin asignar';

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * All producers (including inactive), for the admin/producers.php list.
     */
    public function getAll() {
        $sql = "SELECT * FROM producers ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Active producers for the "Productor" dropdown on the product form.
     */
    public function getAllActive() {
        $sql = "SELECT * FROM producers WHERE active = 1 ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $sql = "SELECT * FROM producers WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Case-insensitive lookup by exact name, for admin/actions/save-producer.php's
     * duplicate-name check (same pattern as SectionRepository's key check).
     */
    public function findByName($name) {
        $sql = "SELECT * FROM producers WHERE LOWER(name) = LOWER(:name) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $name]);
        return $stmt->fetch();
    }

    public function create($name, $active = 1) {
        $sql = "INSERT INTO producers (name, active) VALUES (:name, :active)";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute(['name' => $name, 'active' => $active ? 1 : 0]);
        return $result ? $this->db->lastInsertId() : false;
    }

    public function update($id, $name, $active) {
        $sql = "UPDATE producers SET name = :name, active = :active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'name' => $name, 'active' => $active ? 1 : 0]);
    }

    public function setActive($id, $active) {
        $sql = "UPDATE producers SET active = :active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id, 'active' => $active ? 1 : 0]);
    }

    /**
     * The 'Sin asignar' placeholder every product defaults to (see migration
     * 021) -- looked up by name rather than a hardcoded id.
     */
    public function getPlaceholder() {
        $sql = "SELECT * FROM producers WHERE name = :name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => self::PLACEHOLDER_NAME]);
        return $stmt->fetch();
    }
}
