<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssistantVectorStoreTable extends Migration
{
    public function up()
    {
        Schema::create('assistant_vector_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_id')->constrained()->onDelete('cascade');
            $table->foreignId('vector_store_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('assistant_vector_store');
    }
}