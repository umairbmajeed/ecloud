<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdditionalColumnsToInstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('ecloud')->table('instances', function (Blueprint $table) {
            $table->uuid('appliance_version_id')->after('vpc_id')->default('');
            $table->integer('vcpu_cores')->after('appliance_version_id')->default(0);
            $table->integer('ram_capacity')->after('vcpu_cores')->default(1024);
            $table->boolean('locked')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('ecloud')->table('instances', function (Blueprint $table) {
            $table->dropColumn(['appliance_version_id', 'vcpu_cores', 'ram_capacity', 'locked']);
        });
    }
}
