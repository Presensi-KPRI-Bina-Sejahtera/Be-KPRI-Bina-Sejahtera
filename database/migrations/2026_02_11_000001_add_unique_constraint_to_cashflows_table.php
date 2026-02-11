<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueConstraintToCashflowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cashflows', function (Blueprint $table) {
            $table->unique(['user_id', 'date', 'type'], 'user_date_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cashflows', function (Blueprint $table) {
            $table->dropUnique('user_date_type_unique');
        });
    }
}
