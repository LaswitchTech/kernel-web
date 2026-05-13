<?php

use App\Core\Migration;

class Add2FASupport extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();

        // Add totp_secret to users table (nullable — NULL means 2FA disabled)
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255)'
        );

        // Add totp_enabled_at to users table (nullable — NULL means 2FA never enabled)
        $pdo->exec(
            'ALTER TABLE users ADD COLUMN totp_enabled_at VARCHAR(32)'
        );

        // Create recovery codes table
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth_2fa_recovery_codes (
                id         INTEGER      NOT NULL PRIMARY KEY,
                user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                code_hash  VARCHAR(255) NOT NULL,
                used_at    VARCHAR(255),
                created_at TEXT         NOT NULL,
                UNIQUE(code_hash)
            )'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS auth_2fa_recovery_codes_user_id
             ON auth_2fa_recovery_codes (user_id)'
        );
    }

    public function down(): void
    {
        $pdo = $this->db->pdo();

        $pdo->exec('DROP TABLE IF EXISTS auth_2fa_recovery_codes');

        // SQLite doesn't support DROP COLUMN before 3.35.0 — rebuild table
        $pdo->exec('
            CREATE TABLE users_backup (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                display_name TEXT DEFAULT \'\',
                password_hash TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                email_verified_at VARCHAR(32),
                totp_secret VARCHAR(255),
                totp_enabled_at VARCHAR(32),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');

        try {
            $pdo->exec(
                'INSERT INTO users_backup
                 (id, username, email, display_name, password_hash, is_active,
                  email_verified_at, totp_secret, totp_enabled_at, created_at, updated_at)
                 SELECT id, username, email, display_name, password_hash, is_active,
                        email_verified_at, totp_secret, totp_enabled_at, created_at, updated_at
                 FROM users'
            );
            $pdo->exec('DROP TABLE users');
            $pdo->exec('ALTER TABLE users_backup RENAME TO users');
        } catch (\Throwable) {
            // If migration already rolled back or table doesn't exist, ignore
        }
    }
}
