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
        Schema::table('video_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->renameColumn('status', 'is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_categories', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->renameColumn('is_active', 'status');
        });
    }
};
