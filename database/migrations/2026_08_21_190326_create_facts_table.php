<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('research_run_id')->nullable()->constrained()->nullOnDelete();
            $table->text('statement');
            $table->json('tags')->nullable();
            $table->float('confidence')->default(0.5);
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index('thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facts');
    }
};
