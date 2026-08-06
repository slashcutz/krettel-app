<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('terabox_image')->nullable()->after('poster');
            $table->json('previews')->nullable()->after('terabox_image');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->string('terabox_image')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['terabox_image', 'previews']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('terabox_image');
        });
    }
};
