<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('device_id', 100)->nullable()->index()->after('user_id');
            $table->string('device_type', 50)->nullable()->after('device_id');
            $table->string('ip_address', 45)->nullable()->after('device_type');
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('device_id', 100)->nullable()->index()->after('user_id');
            $table->string('device_type', 50)->nullable()->after('device_id');
            $table->string('ip_address', 45)->nullable()->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn(['device_id', 'device_type', 'ip_address']);
        });

        Schema::table('watch_histories', function (Blueprint $table) {
            $table->dropColumn(['device_id', 'device_type', 'ip_address']);
        });
    }
};
