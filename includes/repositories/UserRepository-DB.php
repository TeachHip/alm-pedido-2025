<?php
/**
 * User Repository
 * Handles admin user authentication and management
 */

require_once __DIR__ . '/../db/database-DB.php';

class UserRepository {
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
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
     * Find user by email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email AND active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword($username, $password) {
        $user = $this->findByUsername($username);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password_hash']);
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
     * Increment the failed-attempt counter; once it reaches
     * MAX_LOGIN_ATTEMPTS, lock the account for LOCKOUT_MINUTES. Interpolates
     * the (non-user-controlled) lockout window directly into the SQL rather
     * than binding it, since a duplicate named placeholder is invalid under
     * real (non-emulated) PDO prepared statements.
     */
    private function registerFailedLogin($userId, $currentAttempts) {
        $newAttempts = $currentAttempts + 1;
        if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
            $sql = "UPDATE users SET failed_login_attempts = :attempts, locked_until = DATE_ADD(NOW(), INTERVAL " . self::LOCKOUT_MINUTES . " MINUTE) WHERE id = :id";
        } else {
            $sql = "UPDATE users SET failed_login_attempts = :attempts WHERE id = :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['attempts' => $newAttempts, 'id' => $userId]);
    }

    private function resetLoginAttempts($userId) {
        $sql = "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $userId]);
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
