<?php

use App\Core\Migration;

class AddTotpPendingAt extends Migration
{
    public function up(): void
    {
        // Column to track unconfirmed TOTP secrets.
        // When totp_secret is set but totp_enabled_at is NULL,
        // 2FA is in "pending" state (setup not yet confirmed).
        $this->db->execute(
            'ALTER TABLE users ADD COLUMN totp_pending_at VARCHAR(32)'
        );
    }

    public function down(): void
    {
        try {
            // SQLite doesn't support DROP COLUMN before 3.35.0 — rebuild
            $this->db->pdo()->exec(
                'CREATE TABLE users_backup (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    display_name TEXT DEFAULT \'\',
                    password_hash TEXT NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    email_verified_at VARCHAR(32),
                    totp_secret VARCHAR(255),
                    totp_enabled_at VARCHAR(32),
                    totp_pending_at VARCHAR(32),
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )'
            );

            try {
                $this->db->execute(
                    'INSERT INTO users_backup
                     SELECT id, username, email, display_name, password_hash, is_active,
                            email_verified_at, totp_secret, totp_enabled_at, totp_pending_at,
                            created_at, updated_at FROM users'
                );
                $this->db->pdo()->exec('DROP TABLE users');
                $this->db->pdo()->exec('ALTER TABLE users_backup RENAME TO users');
            } catch (\Throwable) {
                // Table may not exist; ignore
            }
        } catch (\Throwable) {
            // Ignore errors — graceful degradation
        }
    }
}
