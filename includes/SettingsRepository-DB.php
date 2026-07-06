<?php
/**
 * Settings Repository
 * Handles all database operations for the settings table (key-value global config)
 */

require_once __DIR__ . '/database-DB.php';

class SettingsRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get a setting value by key, or $default if not set
     */
    public function get($key, $default = null) {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    /**
     * Get a setting value as a boolean ('1'/'0' stored value -> true/false)
     */
    public function getBool($key, $default = false) {
        $value = $this->get($key, null);
        if ($value === null) {
            return $default;
        }
        return $value === '1';
    }

    /**
     * Set a setting value (creates it if it doesn't exist yet)
     */
    public function set($key, $value) {
        $sql = "INSERT INTO settings (setting_key, setting_value)
                VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE setting_value = :value_update";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['key' => $key, 'value' => $value, 'value_update' => $value]);
    }

    /**
     * Set a setting value from a boolean
     */
    public function setBool($key, $bool) {
        return $this->set($key, $bool ? '1' : '0');
    }
}
