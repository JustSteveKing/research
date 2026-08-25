<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            // What a complete answer needs, and how much of it is in hand.
            $table->json('coverage')->nullable()->after('embedding');
            $table->timestamp('coverage_mapped_at')->nullable()->after('coverage');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn(['coverage', 'coverage_mapped_at']);
        });
    }
};
