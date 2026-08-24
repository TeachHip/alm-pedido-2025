<?php
/**
 * User Repository
 * Handles admin user authentication and management
 */

require_once __DIR__ . '/../db/database-DB.php';
require_once __DIR__ . '/LoginLockoutTrait.php';

class UserRepository {
    use LoginLockoutTrait;

    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function lockoutTableName() {
        return 'users';
    }

    /**
     * Find user by username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = :username AND active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    /**
     * Authenticate user and return user data if valid. Returns the user row
     * on success, false on wrong credentials, or the string 'locked' if the
     * account is currently in a brute-force lockout (see registerFailedLogin()) --
     * checked before the password so a locked-out attacker can't keep guessing.
     */
    public function authenticate($username, $password) {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }

        if (password_verify($password, $user['password_hash'])) {
            $this->resetLoginAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            return $user;
        }

        $this->registerFailedLogin($user['id'], (int) $user['failed_login_attempts']);
        return false;
    }

    /**
     * Create new user
     */
    public function create($data) {
        $sql = "INSERT INTO users (username, email, password_hash, role, active) 
                VALUES (:username, :email, :password_hash, :role, :active)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'worker',
            'active' => $data['active'] ?? 1
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        $sql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $userId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }
    
    /**
     * Get all users
     */
    public function getAll() {
        $sql = "SELECT id, username, email, role, active, last_login, created_at 
                FROM users 
                ORDER BY username ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        $sql = "SELECT id, username, email, role, active, last_login, created_at 
                FROM users 
                WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
