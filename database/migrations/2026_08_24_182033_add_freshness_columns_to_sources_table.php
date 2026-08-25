<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Only balanced mode tends to carry a date, so this stays nullable
            // and staleness falls back to when the fact was distilled.
            $table->timestamp('published_at')->nullable()->after('reliability');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
