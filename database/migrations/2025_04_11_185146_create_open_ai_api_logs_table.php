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
        Schema::create('open_ai_api_logs', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_id')->nullable()->index();

            $table->text("request")->nullable();
            $table->text("response")->nullable();
            $table->decimal("duration", places: 4)->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_ai_api_logs');
    }
};
