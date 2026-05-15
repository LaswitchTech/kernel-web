<?php

use App\Core\Migration;

class AddPasswordResetSupport extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS auth_password_resets (
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
            'CREATE INDEX IF NOT EXISTS auth_password_resets_user_id
             ON auth_password_resets (user_id)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS auth_password_resets');
    }
}
