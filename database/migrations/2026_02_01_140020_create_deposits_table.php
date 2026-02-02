<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('for_name');
            $table->enum('type', ['simpanan', 'angsuran']);
            $table->date('date');
            $table->unsignedBigInteger('value');
            $table->string('verified_key')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
