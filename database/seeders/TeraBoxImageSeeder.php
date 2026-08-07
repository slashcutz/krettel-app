<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Video;
use App\Services\TeraBoxClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TeraBoxImageSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1626814026160-2237a95fc5a0?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=800&auto=format&fit=crop',
        ];

        $remoteDir = rtrim(config('terabox.remote_dir', '/Apps/Krettel'), '/') . '/Images';

        try {
            $terabox = app(TeraBoxClient::class);
            $terabox->ensureAuthenticated();
            $terabox->createDir($remoteDir);
        } catch (\Throwable $e) {
            $this->command->error('TeraBox auth failed: ' . $e->getMessage());

            return;
        }

        $remotePaths = [];

        foreach ($sources as $index => $url) {
            $tmp = media_temp_dir() . DIRECTORY_SEPARATOR . 'tbimg_' . uniqid('', true);

            try {
                $bytes = @file_get_contents($url);

                if ($bytes === false || $bytes === '') {
                    throw new \RuntimeException('Could not download image: ' . $url);
                }

                file_put_contents($tmp, $bytes);

                $remotePaths[] = $terabox->uploadFile($tmp, $remoteDir, 'poster_' . ($index + 1) . '.jpg');
                $this->command->info('Uploaded poster_' . ($index + 1) . '.jpg');
            } catch (\Throwable $e) {
                Log::error('[TERABOX-IMG] Upload failed for ' . $url . ': ' . $e->getMessage());
                $this->command->warn('Skipped image ' . ($index + 1) . ': ' . $e->getMessage());
            } finally {
                if (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        if (empty($remotePaths)) {
            $this->command->error('No images were uploaded to TeraBox. Nothing to link.');

            return;
        }

        $ref = fn (string $path) => 'terabox://' . $path;

        $linkedVideos = 0;
        Video::orderBy('id')->chunk(50, function ($videos) use ($remotePaths, $ref, &$linkedVideos) {
            foreach ($videos as $video) {
                $video->terabox_image = $ref($remotePaths[array_rand($remotePaths)]);
                $video->previews = collect($remotePaths)->map($ref)->values()->all();
                $video->save();
                $linkedVideos++;
            }
        });

        $linkedCollections = 0;
        Collection::chunk(50, function ($collections) use ($remotePaths, $ref, &$linkedCollections) {
            foreach ($collections as $collection) {
                $collection->terabox_image = $ref($remotePaths[array_rand($remotePaths)]);
                $collection->save();
                $linkedCollections++;
            }
        });

        $this->command->info('Linked ' . $linkedVideos . ' videos and ' . $linkedCollections . ' collections to TeraBox images.');
    }
}
