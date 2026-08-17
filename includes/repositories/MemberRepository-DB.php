<?php
/**
 * Member Repository
 * Handles member (customer) authentication and management. Members log in
 * with phone + password (set in person by Hop, no self-service reset at
 * launch) -- see includes/member-auth.php and AI/plans v10.
 */

require_once __DIR__ . '/../db/database-DB.php';

class MemberRepository {
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_MINUTES = 15;

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
     * Zero-padded 3-digit member_number, e.g. "007" -- shared by the admin
     * member list (shown bare, per Hop's request) and the ticket de compra
     * (shown with an "AM" prefix, e.g. "AM007") -- callers add their own
     * prefix/none. Static since it's a pure format (no DB access).
     */
    public static function formatMemberNumber($memberNumber) {
        return sprintf('%03d', (int) $memberNumber);
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
    /**
     * Returns the member row on success, false on wrong credentials, or the
     * string 'locked' if the account is currently in a brute-force lockout
     * (see registerFailedLogin()) -- checked before the password so a
     * locked-out attacker can't keep guessing.
     */
    public function authenticate($phone, $password) {
        $member = $this->findByPhone($phone);

        if (!$member) {
            return false;
        }

        if ($member['locked_until'] && strtotime($member['locked_until']) > time()) {
            return 'locked';
        }

        if (password_verify($password, $member['password_hash'])) {
            $this->resetLoginAttempts($member['id']);
            $this->updateLastLogin($member['id']);
            return $member;
        }

        $this->registerFailedLogin($member['id'], (int) $member['failed_login_attempts']);
        return false;
    }

    /**
     * Increment the failed-attempt counter; once it reaches
     * MAX_LOGIN_ATTEMPTS, lock the account for LOCKOUT_MINUTES. Interpolates
     * the (non-user-controlled) lockout window directly into the SQL rather
     * than binding it, since a duplicate named placeholder is invalid under
     * real (non-emulated) PDO prepared statements.
     */
    private function registerFailedLogin($memberId, $currentAttempts) {
        $newAttempts = $currentAttempts + 1;
        if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
            $sql = "UPDATE members SET failed_login_attempts = :attempts, locked_until = DATE_ADD(NOW(), INTERVAL " . self::LOCKOUT_MINUTES . " MINUTE) WHERE id = :id";
        } else {
            $sql = "UPDATE members SET failed_login_attempts = :attempts WHERE id = :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['attempts' => $newAttempts, 'id' => $memberId]);
    }

    private function resetLoginAttempts($memberId) {
        $sql = "UPDATE members SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $memberId]);
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
     * Next correlative member_number (1, 2, 3...) -- decoupled from the DB
     * id, never reused, so "highest number ever assigned" always answers
     * "how many members have we ever had". Placeholder rows never have one
     * (NULL), so MAX() here already skips them without needing a WHERE.
     */
    private function nextMemberNumber() {
        $result = $this->db->query("SELECT COALESCE(MAX(member_number), 0) + 1 AS next_number FROM members")->fetch();
        return (int) $result['next_number'];
    }

    /**
     * Create new member. Activation is atomic with creation -- Hop always
     * does this in person, there's no separate "pending activation" state.
     * This is only ever used for real members (placeholder/bookkeeping rows
     * like "OLD member" are created directly via migration), so it always
     * assigns the next member_number.
     */
    public function create($data) {
        $sql = "INSERT INTO members (member_number, phone, alias, internal_alias, notes, email, password_hash, membership_type, activated_at, active)
                VALUES (:member_number, :phone, :alias, :internal_alias, :notes, :email, :password_hash, :membership_type, NOW(), :active)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'member_number' => $this->nextMemberNumber(),
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
     * Get all real members, for the admin members list. Excludes
     * placeholder/bookkeeping rows (see is_placeholder, migration 012) --
     * they were never actual members and shouldn't show up here.
     */
    public function getAll() {
        $sql = "SELECT id, member_number, phone, alias, email, membership_type, activated_at, active, last_login, created_at
                FROM members
                WHERE is_placeholder = 0
                ORDER BY alias ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
