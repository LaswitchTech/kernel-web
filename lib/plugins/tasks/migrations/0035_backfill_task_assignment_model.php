<?php

use App\Core\Migration;

/**
 * Backfill the generic assignment model for existing task rows.
 *
 * ## Context
 *
 * Migration 0034 added the polymorphic assignment columns (assigned_type,
 * assigned_id) alongside the legacy `assigned_user_id` FK.  Rows that existed
 * before that migration — and rows created after it by the current web UI
 * (which still writes only to assigned_user_id) — have:
 *
 *   assigned_type = NULL
 *   assigned_id   = NULL
 *   assigned_user_id = X   (the user's primary key)
 *
 * This migration backfills those rows to the canonical new-model form:
 *
 *   assigned_type = 'user'
 *   assigned_id   = assigned_user_id   (same value)
 *
 * ## Scope
 *
 * Only rows where:
 *   - assigned_user_id IS NOT NULL   (has a legacy user assignment)
 *   - assigned_type IS NULL          (not yet migrated to new model)
 *
 * Rows already populated via the new model (assigned_type IS NOT NULL) are
 * untouched.
 *
 * ## What changes
 *
 * Before backfill:
 *   assigned_user_id = 3, assigned_type = NULL, assigned_id = NULL
 *
 * After backfill:
 *   assigned_user_id = 3, assigned_type = 'user', assigned_id = 3
 *
 * assigned_user_id is NOT cleared — it is kept as a safety net until the
 * write path is fully migrated and the column is retired.
 *
 * ## Rollback
 *
 * down() reverses exactly the rows that up() changed:
 *   - rows where assigned_type = 'user' AND assigned_user_id IS NOT NULL
 *     (i.e. rows that had a legacy user assignment and were backfilled)
 *
 * Rows set via the new write path (after the web UI is updated) would have
 * assigned_user_id = NULL, so they are excluded from the rollback.
 */
class BackfillTaskAssignmentModel extends Migration
{
    public function up(): void
    {
        $this->db->pdo()->exec(
            "UPDATE tasks
             SET    assigned_type = 'user',
                    assigned_id   = assigned_user_id
             WHERE  assigned_user_id IS NOT NULL
               AND  assigned_type IS NULL"
        );
    }

    public function down(): void
    {
        // Reset only the rows this migration touched:
        // rows where assigned_type='user' and assigned_user_id is populated
        // (i.e. they were backfilled from legacy data, not set by the new path).
        $this->db->pdo()->exec(
            "UPDATE tasks
             SET    assigned_type = NULL,
                    assigned_id   = NULL
             WHERE  assigned_type = 'user'
               AND  assigned_user_id IS NOT NULL"
        );
    }
}
