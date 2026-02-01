<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['datang', 'pulang']);
            $table->time('time');
            $table->unsignedInteger('distance');
            $table->timestamps();

            $table->index('user_id');
            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
