<?php
/**
 * Shared brute-force-lockout mechanism for MemberRepository and
 * UserRepository -- previously duplicated near-verbatim between the two
 * (~50 lines, including this exact reasoning about the interpolated
 * lockout window). A future policy or security change to lockout behavior
 * now only needs to happen once. authenticate() itself stays separate per
 * repository (member vs admin login genuinely differ), only the lockout
 * bookkeeping is shared here.
 *
 * PHP traits can't declare their own constants, so MAX_LOGIN_ATTEMPTS/
 * LOCKOUT_MINUTES still have to be defined on each using class -- only the
 * logic that reads them is shared.
 */
trait LoginLockoutTrait {
    /**
     * The table this trait's queries run against ('members' or 'users').
     * Every class using this trait must implement this.
     */
    abstract protected function lockoutTableName();

    /**
     * Increment the failed-attempt counter; once it reaches
     * MAX_LOGIN_ATTEMPTS, lock the account for LOCKOUT_MINUTES. Interpolates
     * the (non-user-controlled) lockout window directly into the SQL rather
     * than binding it, since a duplicate named placeholder is invalid under
     * real (non-emulated) PDO prepared statements.
     */
    private function registerFailedLogin($id, $currentAttempts) {
        $table = $this->lockoutTableName();
        $newAttempts = $currentAttempts + 1;
        if ($newAttempts >= self::MAX_LOGIN_ATTEMPTS) {
            $sql = "UPDATE $table SET failed_login_attempts = :attempts, locked_until = DATE_ADD(NOW(), INTERVAL " . self::LOCKOUT_MINUTES . " MINUTE) WHERE id = :id";
        } else {
            $sql = "UPDATE $table SET failed_login_attempts = :attempts WHERE id = :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['attempts' => $newAttempts, 'id' => $id]);
    }

    private function resetLoginAttempts($id) {
        $table = $this->lockoutTableName();
        $sql = "UPDATE $table SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin($id) {
        $table = $this->lockoutTableName();
        $sql = "UPDATE $table SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
