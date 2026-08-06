<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('collections')->exists()) {
            $this->command->info('Collections already seeded. Skipping.');
            return;
        }

        $videoIds = DB::table('videos')->pluck('id')->all();
        $ownerId = DB::table('users')->value('id');

        $collections = [
            ['name' => 'Action & Thriller', 'image' => 'https://images.unsplash.com/photo-1535016120720-40c646be5580?q=80&w=400&auto=format&fit=crop'],
            ['name' => 'Sci-Fi Universe', 'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?q=80&w=400&auto=format&fit=crop'],
            ['name' => 'Documentaries', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=400&auto=format&fit=crop'],
            ['name' => 'Comedy Favorites', 'image' => 'https://images.unsplash.com/photo-1527224857830-43a7acc85260?q=80&w=400&auto=format&fit=crop'],
            ['name' => 'Top Rated', 'image' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=400&auto=format&fit=crop'],
        ];

        foreach ($collections as $collection) {
            $collectionId = DB::table('collections')->insertGetId([
                'user_id' => $ownerId,
                'name' => $collection['name'],
                'slug' => Str::slug($collection['name']),
                'description' => 'A curated collection of ' . $collection['name'] . ' videos.',
                'image' => $collection['image'],
                'visibility' => 'public',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $selected = collect($videoIds)->shuffle()->take(rand(3, 6))->all();

            foreach (array_values($selected) as $index => $videoId) {
                DB::table('collection_items')->insert([
                    'collection_id' => $collectionId,
                    'video_id' => $videoId,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Seeded ' . count($collections) . ' collections.');
    }
}
