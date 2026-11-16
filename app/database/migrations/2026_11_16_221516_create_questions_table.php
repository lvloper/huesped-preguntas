<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email');
            $table->text('question');
            $table->boolean('selected')->default(false);
            $table->boolean('answered')->default(false);
            $table->timestamps();
            $table->index(['answered', 'selected', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
