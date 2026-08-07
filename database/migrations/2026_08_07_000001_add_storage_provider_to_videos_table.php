<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('storage_provider')->default('local')->after('storage_account_id');
        });

        // Backfill: any video already flagged as remote is TeraBox.
        \Illuminate\Support\Facades\DB::table('videos')
            ->where('video_url', 'terabox-remote')
            ->update(['storage_provider' => 'terabox']);
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('storage_provider');
        });
    }
};
