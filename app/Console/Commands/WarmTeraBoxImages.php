<?php

namespace App\Console\Commands;

use App\Http\Controllers\TeraBoxImageController;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Console\Command;

class WarmTeraBoxImages extends Command
{
    protected $signature = 'images:warm {--force : Re-fetch images already in cache}';

    protected $description = 'Pre-cache optimized TeraBox image bytes so pages load instantly';

    public function handle(): int
    {
        $force = $this->option('force');
        $warmed = 0;
        $skipped = 0;
        $failed = 0;

        $resources = [];

        foreach (Video::select('id', 'terabox_image', 'previews')->get() as $video) {
            foreach (static::teraboxRefs([$video->terabox_image, ...(is_array($video->previews) ? $video->previews : [])]) as $path) {
                $resources[] = ['video', $video->id, $path];
            }
        }

        foreach (Collection::select('id', 'terabox_image')->get() as $collection) {
            foreach (static::teraboxRefs([$collection->terabox_image]) as $path) {
                $resources[] = ['collection', $collection->id, $path];
            }
        }

        $this->info('Found ' . count($resources) . ' references to warm.');

        $seen = [];
        foreach ($resources as [$model, $id, $path]) {
            if (isset($seen[$path])) {
                $skipped++;
                continue;
            }
            $seen[$path] = true;

            try {
                TeraBoxImageController::warm($model, $id, $path, $force);
                $warmed++;
                $this->line("  <info>[OK]</info> {$model} #{$id} — {$path}");
            } catch (\Throwable $e) {
                $failed++;
                $this->line("  <error>[FAIL]</error> {$model} #{$id} — {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Warmed {$warmed}, skipped {$skipped}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected static function teraboxRefs(array $values): array
    {
        $refs = [];

        foreach ($values as $value) {
            if (is_string($value) && str_starts_with($value, 'terabox://')) {
                $refs[] = substr($value, 9);
            }
        }

        return array_values(array_unique($refs));
    }
}
