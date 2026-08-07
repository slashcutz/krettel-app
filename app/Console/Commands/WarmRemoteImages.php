<?php

namespace App\Console\Commands;

use App\Http\Controllers\TeraBoxImageController;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Console\Command;

class WarmRemoteImages extends Command
{
    protected $signature = 'images:warm {--force : Re-fetch images already in cache}';

    protected $description = 'Pre-cache optimized remote (TeraBox/Pixeldrain) image bytes so pages load instantly';

    public function handle(): int
    {
        $force = $this->option('force');
        $warmed = 0;
        $skipped = 0;
        $failed = 0;

        $resources = [];

        foreach (Video::select('id', 'terabox_image', 'previews')->get() as $video) {
            foreach (static::remoteRefs([$video->terabox_image, ...(is_array($video->previews) ? $video->previews : [])]) as $ref) {
                $resources[] = ['video', $video->id, $ref];
            }
        }

        foreach (Collection::select('id', 'terabox_image')->get() as $collection) {
            foreach (static::remoteRefs([$collection->terabox_image]) as $ref) {
                $resources[] = ['collection', $collection->id, $ref];
            }
        }

        foreach (['navbar_logo'] as $key) {
            foreach (static::remoteRefs([\App\Models\Setting::get($key)]) as $ref) {
                $resources[] = ['settings', $key, $ref];
            }
        }

        $this->info('Found ' . count($resources) . ' references to warm.');

        $seen = [];
        foreach ($resources as [$model, $id, $ref]) {
            if (isset($seen[$ref])) {
                $skipped++;
                continue;
            }
            $seen[$ref] = true;

            try {
                TeraBoxImageController::warm($model, $id, $ref, $force);
                $warmed++;
                $this->line("  <info>[OK]</info> {$model} #{$id} — {$ref}");
            } catch (\Throwable $e) {
                $failed++;
                $this->line("  <error>[FAIL]</error> {$model} #{$id} — {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Warmed {$warmed}, skipped {$skipped}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected static function remoteRefs(array $values): array
    {
        $refs = [];

        foreach ($values as $value) {
            [$scheme, $remoteKey] = TeraBoxImageController::parseRef($value);

            if ($scheme !== null && $remoteKey !== null) {
                $refs[] = $value;
            }
        }

        return array_values(array_unique($refs));
    }
}
