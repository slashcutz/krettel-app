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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('full_description')->nullable();
            
            $table->foreignId('category_id')->constrained('video_categories')->cascadeOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            
            $table->string('tags')->nullable();
            $table->date('release_date')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->string('age_rating')->nullable();
            $table->string('video_type')->default('movie'); // movie, tv_show
            
            $table->string('thumbnail')->nullable();
            $table->string('poster')->nullable();
            $table->string('banner')->nullable();
            $table->string('logo')->nullable();
            $table->string('trailer_url')->nullable();
            
            $table->foreignId('storage_account_id')->nullable()->constrained('storage_accounts')->nullOnDelete();
            $table->string('storage_folder')->nullable();
            $table->string('video_url')->nullable();
            $table->string('resolution')->nullable();
            $table->string('quality')->nullable();
            $table->string('codec')->nullable();
            $table->string('bitrate')->nullable();
            
            $table->enum('visibility', ['public', 'private', 'draft', 'scheduled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('og_image')->nullable();
            
            $table->integer('views')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
