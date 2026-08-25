<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Which question an answer belongs to. Two questions asked in
            // quick succession each get their own stream, and the pairing
            // stops the first answer being filed under the second question.
            $table->foreignId('replies_to_id')->nullable()->after('role')->constrained('chat_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replies_to_id');
        });
    }
};
