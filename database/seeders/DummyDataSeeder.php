<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $catId = DB::table('video_categories')->insertGetId([
            'name' => 'Movies',
            'slug' => 'movies',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $videoId = DB::table('videos')->insertGetId([
            'title' => 'Dune: Part Two',
            'slug' => 'dune-part-two',
            'short_description' => 'Paul Atreides unites with Chani and the Fremen.',
            'full_description' => 'Paul Atreides unites with Chani and the Fremen while on a warpath of revenge against the conspirators who destroyed his family.',
            'category_id' => $catId,
            'duration' => 9960,
            'age_rating' => 'PG-13',
            'thumbnail' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1925&auto=format&fit=crop',
            'poster' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1925&auto=format&fit=crop',
            'banner' => 'https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=2070&auto=format&fit=crop',
            'video_url' => 'https://vjs.zencdn.net/v/oceans.mp4',
            'visibility' => 'public',
            'views' => 15000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        for ($i=1; $i<=10; $i++) {
            DB::table('videos')->insert([
                'title' => 'Sample Trending Movie ' . $i,
                'slug' => Str::slug('Sample Trending Movie ' . $i),
                'short_description' => 'This is a sample trending movie.',
                'category_id' => $catId,
                'duration' => 7200,
                'age_rating' => 'TV-MA',
                'thumbnail' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=400&auto=format&fit=crop',
                'video_url' => 'https://vjs.zencdn.net/v/oceans.mp4',
                'visibility' => 'public',
                'views' => rand(100, 10000),
                'created_at' => now()->subDays($i),
                'updated_at' => now(),
            ]);
        }

        DB::table('banners')->insert([
            'video_id' => $videoId,
            'title' => 'Dune: Part Two',
            'subtitle' => 'Now Streaming',
            'image_url' => 'https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=2070&auto=format&fit=crop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
    }
}
