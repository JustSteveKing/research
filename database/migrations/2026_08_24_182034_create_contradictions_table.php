<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contradictions', function (Blueprint $table) {
            $table->id();
            // Both sides are stored once, ordered by id, and read from either
            // fact through Fact::contradictions().
            $table->foreignId('fact_id')->constrained('facts')->cascadeOnDelete();
            $table->foreignId('other_fact_id')->constrained('facts')->cascadeOnDelete();
            $table->foreignId('detected_run_id')->nullable()->constrained('research_runs')->nullOnDelete();
            $table->float('similarity')->default(0);
            $table->text('note')->nullable();
            // Set when the reader decides which side wins, so the pair stops
            // being flagged without losing the record that it was disputed.
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['fact_id', 'other_fact_id']);
            $table->index('other_fact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contradictions');
    }
};
