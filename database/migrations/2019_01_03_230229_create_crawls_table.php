<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCrawlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_usluge');
            $table->string('naziv');
            $table->boolean('e_usluga');
            $table->boolean('dokument');
            $table->boolean('privreda');
            $table->boolean('zakazivanje');
            $table->timestamp('vreme');

            $table->unique('id_usluge');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crawls');
    }
}
