<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend community_moderation_logs.action enum so the admin moderator UI can
 * record manual actions: hide / flag / warn (in addition to the original
 * auto_block / auto_flag / approve / remove / restore / dodo_reply set).
 *
 * MariaDB / MySQL: ALTER TABLE ... MODIFY COLUMN with full enum list.
 * SQLite (test env): doesn't support real enum; the original migration
 * created a CHECK constraint, so we drop & recreate the column on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Drop the CHECK constraint by recreating the column (SQLite has
            // no ALTER COLUMN). We use a temp string column, then rename.
            Schema::table('community_moderation_logs', function (Blueprint $table): void {
                $table->string('action_new')->nullable();
            });
            DB::statement('UPDATE community_moderation_logs SET action_new = action');
            Schema::table('community_moderation_logs', function (Blueprint $table): void {
                $table->dropColumn('action');
            });
            Schema::table('community_moderation_logs', function (Blueprint $table): void {
                $table->renameColumn('action_new', 'action');
            });
            // No CHECK constraint added back — application enforces via fillable.
            return;
        }

        // MariaDB / MySQL — keep enum guarantee.
        DB::statement(
            "ALTER TABLE community_moderation_logs MODIFY COLUMN action ENUM(".
            "'auto_block','auto_flag','approve','remove','restore','dodo_reply',".
            "'hide','flag','warn'".
            ") NOT NULL"
        );
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            // Best-effort no-op; original CHECK is non-trivial to rebuild here.
            return;
        }
        DB::statement(
            "ALTER TABLE community_moderation_logs MODIFY COLUMN action ENUM(".
            "'auto_block','auto_flag','approve','remove','restore','dodo_reply'".
            ") NOT NULL"
        );
    }
};
