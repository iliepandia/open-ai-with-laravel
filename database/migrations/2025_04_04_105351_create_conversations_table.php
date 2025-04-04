<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('user_id');

            $table->string('assistant_id', 29)->index() ;
            $table->string('thread_id', 31)->index() ;
            $table->string('run_id', 28)->index() ;
            $table->string('source', 20)->index();

            $table->text('message')->nullable();
            $table->text('annotations')->nullable();

            $table->tinyInteger('feedback')->nullable();
            $table->text('feedback_text')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
