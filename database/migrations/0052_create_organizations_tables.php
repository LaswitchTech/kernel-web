<?php

use App\Core\Migration;

class CreateOrganizationsTables extends Migration
{
    public function up(): void
    {
        $pdo = $this->db->pdo();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS organizations (
                id          INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
                name        TEXT         NOT NULL,
                slug        TEXT         NOT NULL UNIQUE,
                type        TEXT         NOT NULL DEFAULT 'organization',
                active      INTEGER      NOT NULL DEFAULT 1,
                created_at  VARCHAR(32)  NOT NULL,
                updated_at  VARCHAR(32)  NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS organization_users (
                id              INTEGER      NOT NULL PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER      NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
                user_id         INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                role            TEXT         NOT NULL DEFAULT 'member',
                is_default      INTEGER      NOT NULL DEFAULT 0,
                created_at      VARCHAR(32)  NOT NULL,
                updated_at      VARCHAR(32),
                UNIQUE(organization_id, user_id)
            )
        ");

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organization_users_user_id ON organization_users (user_id)'
        );

        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS organization_users_org_id ON organization_users (organization_id)'
        );
    }

    public function down(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec('DROP TABLE IF EXISTS organization_users');
        $pdo->exec('DROP TABLE IF EXISTS organizations');
    }
}
