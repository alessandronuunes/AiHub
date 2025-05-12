<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->string('vector_store_id')->nullable()->after('file_ids');
        });
    }

    public function down()
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn('vector_store_id');
        });
    }
};