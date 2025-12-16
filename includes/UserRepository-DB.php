<?php
/**
 * User Repository
 * Handles admin user authentication and management
 */

require_once __DIR__ . '/database-DB.php';

class UserRepository {
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
     * Authenticate user and return user data if valid
     */
    public function authenticate($username, $password) {
        $user = $this->findByUsername($username);
        
        if (!$user) {
            return false;
        }
        
        if (password_verify($password, $user['password_hash'])) {
            // Update last login
            $this->updateLastLogin($user['id']);
            return $user;
        }
        
        return false;
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
