<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInstitutionIdToCrawlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->unsignedInteger('id_institucije')
                ->after('id_usluge')
                ->references('id_institucije')
                ->on('institutions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropColumn('id_institucije');
        });
    }
}
