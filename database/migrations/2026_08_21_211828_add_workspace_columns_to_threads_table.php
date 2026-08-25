<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            // Powers the "since you left" strip and the rail's unread markers.
            $table->timestamp('last_opened_at')->nullable()->after('knowledge_error');
            $table->timestamp('pinned_at')->nullable()->after('last_opened_at');
            // What the local model is doing for this thread right now.
            $table->string('knowledge_state')->default('idle')->after('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn(['last_opened_at', 'pinned_at', 'knowledge_state']);
        });
    }
};
