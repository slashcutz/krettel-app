<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the chunked-upload token on the video row so the notification
     * dropdown can look up the live upload/push progress (cache keyed by the
     * token) without any other linkage.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('upload_token')->nullable()->after('storage_folder');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('upload_token');
        });
    }
};
