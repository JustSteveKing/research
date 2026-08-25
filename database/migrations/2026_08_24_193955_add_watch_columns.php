<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            // Re-research the seed question on a cadence and report what moved.
            $table->string('watch_cadence')->nullable()->after('auto_follow_up');
            $table->timestamp('watched_at')->nullable()->after('watch_cadence');
            $table->json('last_watch_report')->nullable()->after('watched_at');
        });

        Schema::table('research_runs', function (Blueprint $table) {
            // A watch run is compared against what was already known; an
            // ordinary run is not, so the two cannot share a code path.
            $table->boolean('is_watch')->default(false)->after('depth');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn(['watch_cadence', 'watched_at', 'last_watch_report']);
        });

        Schema::table('research_runs', function (Blueprint $table) {
            $table->dropColumn('is_watch');
        });
    }
};
