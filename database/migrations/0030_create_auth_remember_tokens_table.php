<?php

use App\Core\Migration;

class CreateAuthRememberTokensTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS auth_remember_tokens (
                id            INTEGER      NOT NULL,
                user_id       INTEGER      NOT NULL,
                selector      VARCHAR(128) NOT NULL,
                token_hash    VARCHAR(255) NOT NULL,
                expires_at    VARCHAR(32)  NOT NULL,
                last_used_at  VARCHAR(32),
                user_agent_hash VARCHAR(64),
                ip_hash       VARCHAR(64),
                revoked_at    VARCHAR(32),
                created_at    VARCHAR(32)  NOT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        $this->db->pdo()->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS auth_remember_tokens_selector_unique
             ON auth_remember_tokens (selector)'
        );

        $this->db->pdo()->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS auth_remember_tokens_token_hash_unique
             ON auth_remember_tokens (token_hash)'
        );

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS auth_remember_tokens_user_id
             ON auth_remember_tokens (user_id)'
        );

        $this->db->pdo()->exec(
            'CREATE INDEX IF NOT EXISTS auth_remember_tokens_expires_at
             ON auth_remember_tokens (expires_at)'
        );
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS auth_remember_tokens');
    }
}
