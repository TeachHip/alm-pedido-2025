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
     * Authenticate user and return user data if valid, or false on wrong
     * credentials. Admin login uses an escalating delay instead of the
     * hard lockout member login still uses (LoginLockoutTrait) -- see
     * registerFailedLoginWithDelay() below. Changed 2026-09-13: a hard
     * lockout has no real recovery path when the account being locked is
     * the only admin there is (an in-panel unlock button is useless to
     * someone who can't get into the panel) -- a growing delay means a
     * legitimate admin who knows the right password can always get back in
     * immediately, while a brute-force attacker's guess rate still
     * collapses exponentially. Never returns 'locked' -- no lockout state
     * exists to check.
     */
    public function authenticate($username, $password) {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            $this->resetLoginAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            return $user;
        }

        $this->registerFailedLoginWithDelay($user['id'], (int) $user['failed_login_attempts']);
        return false;
    }

    /**
     * Records the failed attempt, then makes THIS request sleep() before
     * returning if enough attempts have piled up -- the "lockout" is a
     * growing wait imposed on the wrong-password response itself, never a
     * stored "come back after X" wall. No delay for the first 2 wrong
     * attempts (an honest typo shouldn't cost anything); doubles from the
     * 3rd attempt onward, capped at 30s so a single request can't tie up a
     * worker process indefinitely on this small/shared host. Deliberately
     * NOT the shared LoginLockoutTrait::registerFailedLogin() (which sets
     * locked_until) -- this class defines its own method of the same
     * pattern but different behavior; member login is untouched.
     */
    private function registerFailedLoginWithDelay($userId, $currentAttempts) {
        $newAttempts = $currentAttempts + 1;
        $sql = "UPDATE users SET failed_login_attempts = :attempts WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['attempts' => $newAttempts, 'id' => $userId]);

        if ($newAttempts >= 3) {
            $delaySeconds = min(2 ** ($newAttempts - 2), 30);
            sleep($delaySeconds);
        }
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

    /**
     * Clear the brute-force lockout on every admin/worker account at once --
     * used by admin/login.php's fluffy.flag emergency-unlock check. "All"
     * rather than one username since the point is recovering access with no
     * way to look anything up first.
     */
    public function unlockAll() {
        $sql = "UPDATE users SET failed_login_attempts = 0, locked_until = NULL";
        return $this->db->exec($sql);
    }
}
