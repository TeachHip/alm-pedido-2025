<?php
/**
 * Member Repository
 * Handles member (customer) authentication and management. Members log in
 * with phone + password (set in person by Hop, no self-service reset at
 * launch) -- see includes/member-auth.php and AI/plans v10.
 */

require_once __DIR__ . '/../db/database-DB.php';

class MemberRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Normalize a phone number to a canonical +<country><digits> form.
     * Spain-only app: a bare 9-digit number is assumed to be Spanish and
     * gets +34 prepended; anything already carrying a country code (via
     * a leading + or 00) is kept as entered.
     */
    public function normalizePhone($phone) {
        $digits = preg_replace('/\D/', '', trim((string) $phone));
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9) {
            $digits = '34' . $digits;
        }
        return '+' . $digits;
    }

    /**
     * Strip the (near-always +34) country code for compact admin display.
     * Purely cosmetic -- storage/lookup always use the full normalized form.
     */
    public function formatPhoneForDisplay($phone) {
        return preg_replace('/^\+34/', '', (string) $phone);
    }

    /**
     * Find member by phone (normalizes before lookup). Only active members.
     */
    public function findByPhone($phone) {
        $sql = "SELECT * FROM members WHERE phone = :phone AND active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['phone' => $this->normalizePhone($phone)]);
        return $stmt->fetch();
    }

    /**
     * Find member by ID
     */
    public function findById($id) {
        $sql = "SELECT * FROM members WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Authenticate a member and return their row if valid
     */
    public function authenticate($phone, $password) {
        $member = $this->findByPhone($phone);

        if (!$member) {
            return false;
        }

        if (password_verify($password, $member['password_hash'])) {
            $this->updateLastLogin($member['id']);
            return $member;
        }

        return false;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($memberId) {
        $sql = "UPDATE members SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $memberId]);
    }

    /**
     * Create new member. Activation is atomic with creation -- Hop always
     * does this in person, there's no separate "pending activation" state.
     */
    public function create($data) {
        $sql = "INSERT INTO members (phone, alias, internal_alias, notes, email, password_hash, membership_type, activated_at, active)
                VALUES (:phone, :alias, :internal_alias, :notes, :email, :password_hash, :membership_type, NOW(), :active)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'phone' => $this->normalizePhone($data['phone']),
            'alias' => $data['alias'],
            'internal_alias' => $data['internal_alias'] ?? null,
            'notes' => $data['notes'] ?? null,
            'email' => $data['email'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'membership_type' => $data['membership_type'],
            'active' => $data['active'] ?? 1
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Update a member's profile fields (not the password -- see
     * updatePassword() for that, kept separate since it also revokes
     * the active session).
     */
    public function update($memberId, $data) {
        $sql = "UPDATE members
                SET phone = :phone,
                    alias = :alias,
                    internal_alias = :internal_alias,
                    notes = :notes,
                    email = :email,
                    membership_type = :membership_type,
                    active = :active
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $memberId,
            'phone' => $this->normalizePhone($data['phone']),
            'alias' => $data['alias'],
            'internal_alias' => $data['internal_alias'] ?? null,
            'notes' => $data['notes'] ?? null,
            'email' => $data['email'] ?? null,
            'membership_type' => $data['membership_type'],
            'active' => $data['active'] ?? 1
        ]);
    }

    /**
     * Update a member's password. Clears session_token so any already-open
     * session on a lost/stolen phone is forced to log in again.
     */
    public function updatePassword($memberId, $newPassword) {
        $sql = "UPDATE members SET password_hash = :password_hash, session_token = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $memberId,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Set the member's active session token (single-device: a new login
     * overwrites and effectively logs out any other device).
     */
    public function setSessionToken($memberId, $token) {
        $sql = "UPDATE members SET session_token = :token, session_last_seen_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $memberId, 'token' => $token]);
    }

    /**
     * Clear the session token, forcing logout everywhere (used on explicit
     * logout and as the admin-side "revoke session" action).
     */
    public function clearSessionToken($memberId) {
        $sql = "UPDATE members SET session_token = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $memberId]);
    }

    /**
     * Refresh session_last_seen_at -- the authoritative rolling-expiry
     * check lives in includes/member-auth.php, this just records the visit.
     */
    public function touchLastSeen($memberId) {
        $sql = "UPDATE members SET session_last_seen_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $memberId]);
    }

    /**
     * Set active/inactive without touching anything else
     */
    public function setActive($memberId, $active) {
        $sql = "UPDATE members SET active = :active WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $memberId, 'active' => $active ? 1 : 0]);
    }

    /**
     * Get all members, for the admin members list
     */
    public function getAll() {
        $sql = "SELECT id, phone, alias, email, membership_type, activated_at, active, last_login, created_at
                FROM members
                ORDER BY alias ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
