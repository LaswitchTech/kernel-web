<?php

use App\Core\Migration;

/**
 * Create the catalog_extensions table.
 *
 * Stores extension metadata managed through the admin catalog workflow:
 * submissions, reviews, install/enable status, and distribution metadata.
 */
class CreateCatalogExtensionsTable extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec("
            CREATE TABLE IF NOT EXISTS catalog_extensions (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                name          TEXT    NOT NULL,
                slug          TEXT    NOT NULL UNIQUE,
                type          TEXT    NOT NULL CHECK(type IN ('plugin', 'theme', 'layout')),
                version       TEXT    NOT NULL DEFAULT '0.0.0',
                description   TEXT    NOT NULL DEFAULT '',
                author        TEXT    NOT NULL DEFAULT '',
                download_url  TEXT    NOT NULL DEFAULT '',
                repo_url      TEXT    NULL,
                requirements  TEXT    NOT NULL DEFAULT '[]',
                dependencies  TEXT    NOT NULL DEFAULT '[]',
                status        TEXT    NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
                is_installed  INTEGER NOT NULL DEFAULT 0 CHECK(is_installed IN (0, 1)),
                is_enabled    INTEGER NOT NULL DEFAULT 0 CHECK(is_enabled IN (0, 1)),
                checksum      TEXT    NULL,
                created_at    VARCHAR(32) NOT NULL,
                updated_at    VARCHAR(32) NOT NULL
            )
        ");
    }

    public function down(): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS catalog_extensions');
    }
}
