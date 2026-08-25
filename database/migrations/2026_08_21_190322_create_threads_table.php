<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('seed_question');
            $table->string('status')->default('idle');
            $table->string('mode')->default('fast');
            $table->unsignedInteger('credit_budget')->default(2000);
            $table->unsignedInteger('credits_reserved')->default(0);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->boolean('auto_follow_up')->default(true);
            $table->unsignedTinyInteger('max_depth')->default(3);
            $table->longText('brief')->nullable();
            $table->timestamp('brief_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
