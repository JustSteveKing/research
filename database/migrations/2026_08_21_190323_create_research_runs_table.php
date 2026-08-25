<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_run_id')->nullable()->constrained('research_runs')->nullOnDelete();
            $table->text('query');
            $table->string('mode')->default('fast');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('status')->default('queued');
            $table->string('phase')->nullable();
            $table->longText('report')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('credits_cost')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_runs');
    }
};
