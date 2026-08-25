<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('first_run_id')->nullable()->constrained('research_runs')->nullOnDelete();
            $table->text('url');
            $table->string('domain')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('relevance')->nullable();
            $table->string('reliability')->nullable();
            $table->json('claims')->nullable();
            $table->unsignedInteger('cited_count')->default(1);
            $table->boolean('distilled')->default(false);
            $table->timestamps();

            $table->unique(['thread_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
