<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['pemasukan', 'pengeluaran']);
            $table->unsignedBigInteger('value');
            $table->timestamps();

            $table->index('user_id');
            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflows');
    }
};
