<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spawned_run_id')->nullable()->constrained('research_runs')->nullOnDelete();
            $table->text('question');
            $table->string('origin')->default('ollama');
            $table->string('status')->default('pending');
            $table->float('score')->default(0.5);
            $table->text('rationale')->nullable();
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_questions');
    }
};
