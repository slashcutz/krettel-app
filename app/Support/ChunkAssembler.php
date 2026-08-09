<?php

namespace App\Support;

use RuntimeException;

class ChunkAssembler
{
    /**
     * Merge chunk files ("0", "1", ...) inside $chunkDir into a single
     * stitched file under storage/app/private/pending-uploads/.
     *
     * Stitching runs in the background queue worker (no HTTP/gateway
     * timeout), which is why multi-GB uploads no longer hang the final
     * chunk request.
     *
     * @return string absolute path of the stitched file
     */
    public static function stitch(string $chunkDir, string $token, string $originalFilename): string
    {
        if (! is_dir($chunkDir)) {
            throw new RuntimeException('Chunk dir not found: ' . $chunkDir);
        }

        $metaTotal = (int) @file_get_contents($chunkDir . '/.total');

        // Always derive the count from the numbered chunk files actually
        // present and take the max with any .total meta. This makes legacy
        // dirs (written before .total existed) stitch correctly and protects
        // against a resume reporting a mismatched chunk size/count.
        $scanTotal = 0;
        foreach (glob($chunkDir . '/*') ?: [] as $f) {
            $base = basename($f);
            if (ctype_digit($base)) {
                $scanTotal = max($scanTotal, (int) $base + 1);
            }
        }
        $expectedTotal = max($metaTotal, $scanTotal);

        $received = 0;
        for ($i = 0; $i < $expectedTotal; $i++) {
            if (file_exists($chunkDir . '/' . $i)) {
                $received++;
            }
        }

        if ($expectedTotal > 0 && $received < $expectedTotal) {
            throw new RuntimeException('Incomplete chunk set: ' . $received . '/' . $expectedTotal . ' chunks in ' . $chunkDir);
        }

        if (! is_dir(storage_path('app/private/pending-uploads'))) {
            mkdir(storage_path('app/private/pending-uploads'), 0777, true);
        }

        $cleanName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $originalFilename) ?: 'video.mp4';
        $stitchedRelative = 'pending-uploads/' . $token . '_' . $cleanName;
        $finalAbs = storage_path('app/private/' . $stitchedRelative);

        if (file_exists($finalAbs)) {
            @unlink($finalAbs);
        }

        $out = fopen($finalAbs, 'wb');
        $count = 0;
        for ($i = 0; $i < $expectedTotal; $i++) {
            $inPath = $chunkDir . '/' . $i;
            if (file_exists($inPath)) {
                $in = fopen($inPath, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                $count++;
            }
        }
        fclose($out);

        if ($count === 0 || filesize($finalAbs) === 0) {
            @unlink($finalAbs);
            throw new RuntimeException('No chunks found to stitch in ' . $chunkDir);
        }

        return $finalAbs;
    }

    /**
     * Remove the chunk directory (and any meta files inside it).
     */
    public static function cleanup(string $chunkDir): void
    {
        if (! is_dir($chunkDir)) {
            return;
        }

        foreach (glob($chunkDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($chunkDir);
    }
}