<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('ecloud')->table('host_specs', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('ucs_specification_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('ecloud')->table('host_specs', function (Blueprint $table) {
            $table->dropColumn(['is_hidden']);
        });
    }
};
