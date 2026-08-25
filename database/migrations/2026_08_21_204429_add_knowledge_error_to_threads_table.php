<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            // Why the local knowledge is stale: distillation or the brief failed.
            $table->text('knowledge_error')->nullable()->after('brief_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('knowledge_error');
        });
    }
};
