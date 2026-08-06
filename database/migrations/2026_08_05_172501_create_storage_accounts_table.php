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
        Schema::create('storage_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['local', 's3', 'wasabi', 'digitalocean', 'bunnycdn']);
            $table->string('bucket')->nullable();
            $table->string('access_key')->nullable();
            $table->string('secret_key')->nullable();
            $table->string('region')->nullable();
            $table->string('endpoint')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_accounts');
    }
};
