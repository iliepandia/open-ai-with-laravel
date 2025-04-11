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
        Schema::table('open_ai_messages', function (Blueprint $table) {

            $table->string('type', 20)->after('run_id')->nullable()->index();
            $table->text('raw_response')->after('prompt')->nullable();
            $table->text('metadata')->after('raw_response')->nullable();
            $table->text('notes')->after('metadata')->nullable();

            $table->dropColumn('raw_message');
            $table->dropColumn('raw_annotations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_ai_messages', function (Blueprint $table) {
            $table->text('raw_message')->nullable();
            $table->text('raw_annotations')->nullable();

            $table->dropColumn('type');
            $table->dropColumn('raw_response');
            $table->dropColumn('metadata');
            $table->dropColumn('notes');
        });
    }
};
