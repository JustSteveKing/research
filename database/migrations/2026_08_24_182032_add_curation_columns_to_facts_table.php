<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facts', function (Blueprint $table) {
            // Curated facts survive a rebuild; distilled ones are disposable.
            $table->timestamp('pinned_at')->nullable()->after('embedding');
            $table->timestamp('edited_at')->nullable()->after('pinned_at');
            // Dismissed facts stay in the table so a later run cannot re-add them.
            $table->timestamp('dismissed_at')->nullable()->after('edited_at');
            // When the claim was true, which is the source's publication date
            // where we know it and the distillation date otherwise.
            $table->timestamp('asserted_at')->nullable()->after('dismissed_at');

            $table->index(['thread_id', 'dismissed_at']);
        });

        DB::table('facts')->update(['asserted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('facts', function (Blueprint $table) {
            $table->dropIndex(['thread_id', 'dismissed_at']);
            $table->dropColumn(['pinned_at', 'edited_at', 'dismissed_at', 'asserted_at']);
        });
    }
};
