<?php

/**
 * Migration 0054 — Add totp_setup_id column for stale-QR protection.
 *
 * The totp_setup_id binds each pending 2FA secret to a unique QR code session.
 * This prevents the enable endpoint from accepting codes from a stale QR code
 * if a new generate call overwrote the pending secret.
 */

use App\Core\Migration;

class Migration0054AddTotpSetupId extends Migration
{
    public function up(): void
    {
        $this->db->execute(
            'ALTER TABLE users ADD COLUMN totp_setup_id VARCHAR(255)'
        );
    }

    public function down(): void
    {
        // SQLite 3.35+ supports DROP COLUMN. For older versions, skip.
        try {
            $this->db->pdo()->exec('ALTER TABLE users DROP COLUMN totp_setup_id');
        } catch (\Throwable) {
            // Ignore — non-essential column, graceful degradation.
        }
    }
}
