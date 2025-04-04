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
        Schema::create('open_ai_messages', function (Blueprint $table) {
            $table->id();

            $table->string('assistant_id', 29)->index() ;
            $table->string('thread_id', 31)->index() ;
            $table->string('run_id', 28)->index() ;

            $table->text('prompt')->nullable();
            $table->text('raw_message')->nullable();
            $table->text('raw_annotations')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_ai_messages');
    }
};
