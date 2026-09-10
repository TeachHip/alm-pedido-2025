<?php
/**
 * Database Connection Manager
 * Provides singleton PDO connection with error handling
 */

// Explicit app-wide timezone -- without this, PHP falls back to whatever
// the server's php.ini default happens to be (UTC here locally), which
// would make admin-entered deadlines (see settings.php's
// deadline_pedido_expres/deadline_pedido_grupo) silently wrong by 1-2h.
// Set here since this file loads on every DB-touching request.
date_default_timezone_set('Europe/Madrid');

require_once __DIR__ . '/config-DB.php';

class Database {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            // MySQL's own NOW()/CURRENT_TIMESTAMP (used throughout for
            // created_at, paid_at, session timestamps, lockout timers, ...)
            // otherwise runs on the DB server's own system timezone, not
            // PHP's -- confirmed on production to be off by several hours
            // from Europe/Madrid (2026-09-09: MySQL NOW() read 07:00 while
            // the real Madrid time was ~13:00). Force the session to
            // Madrid's current UTC offset explicitly, computed fresh here
            // (not hardcoded) so it keeps tracking the twice-yearly DST
            // change (+01:00 winter / +02:00 summer) with no manual update
            // needed. A numeric offset, not the named zone 'Europe/Madrid'
            // -- many hosts don't have MySQL's zoneinfo tables loaded, and
            // SET time_zone = 'Europe/Madrid' just silently fails on those.
            $madridTz = new DateTimeZone('Europe/Madrid');
            $offsetSeconds = $madridTz->getOffset(new DateTime('now', $madridTz));
            $madridOffset = sprintf('%+03d:%02d', intdiv($offsetSeconds, 3600), abs($offsetSeconds % 3600) / 60);

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . ", time_zone = '" . $madridOffset . "'"
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please contact administrator.");
            }
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
