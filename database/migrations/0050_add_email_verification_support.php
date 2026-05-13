<?php

use App\Core\Migration;

class AddEmailVerificationSupport extends Migration
{
    public function up(): void
    {
        // Add email_verified_at to users table (nullable, NULL = not verified)
        $this->db->pdo()->exec(
            'ALTER TABLE users ADD COLUMN email_verified_at VARCHAR(32)'
        );

        // Create auth_email_verifications table
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS auth_email_verifications (
                id            INTEGER      NOT NULL PRIMARY KEY,
                user_id       INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash    VARCHAR(255) NOT NULL,
                expires_at    VARCHAR(32)  NOT NULL,
                used_at       VARCHAR(32),
                created_at    TEXT         NOT NULL,
                UNIQUE(token_hash)
            )'
        );

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS auth_email_verifications_user_id
             ON auth_email_verifications (user_id)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS auth_email_verifications');

        // SQLite doesn't support DROP COLUMN before 3.35.0.
        // Rebuild the table to remove the column.
        $this->db->pdo()->exec('
            CREATE TABLE users_backup (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                display_name TEXT DEFAULT \'\',
                password_hash TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ');

        try {
            $this->db->pdo()->exec(
                'INSERT INTO users_backup (id, username, email, display_name, password_hash, is_active, created_at, updated_at)
                 SELECT id, username, email, display_name, password_hash, is_active, created_at, updated_at FROM users'
            );
            $this->db->pdo()->exec('DROP TABLE users');
            $this->db->pdo()->exec('ALTER TABLE users_backup RENAME TO users');
        } catch (\Throwable) {
            // If migration already rolled back or table doesn't exist, ignore
        }
    }
}
