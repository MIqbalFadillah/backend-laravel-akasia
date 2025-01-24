<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterNumberColumnInDebitCardsTable extends Migration
{
    public function up()
    {
        Schema::table('debit_cards', function (Blueprint $table) {
            $table->string('number', 20)->change();
        });
    }

    public function down()
    {
        Schema::table('debit_cards', function (Blueprint $table) {
            $table->bigInteger('number')->change(); // Sesuaikan dengan tipe data sebelumnya
        });
    }
}