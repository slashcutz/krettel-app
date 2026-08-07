<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\VideoAudioTrack;
use App\Models\Language;
use App\Jobs\TranscodeVideoToHls;
use App\Jobs\UploadVideoToTeraBox;
use App\Support\LanguageCodes;
use App\Support\MediaProbe;
use App\Support\PixeldrainImageStore;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoUploadController extends Controller
{
    public function index()
    {
        $categories = \App\Models\VideoCategory::orderBy('sort_order', 'asc')->get();
        return view('frontend.upload.index', compact('categories'));
    }

    public function store(Request $request)
    {
        Log::channel('krettel')->info('[UPLOAD] Upload request received.', [
            'title' => $request->input('title'),
            'storage_provider' => $request->input('storage_provider', 'local'),
            'has_video_file' => $request->hasFile('video_file'),
            'has_thumbnail' => $request->hasFile('thumbnail'),
            'user_id' => auth()->id(),
        ]);

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'category_id' => 'required|integer',
                'visibility' => 'required|string|in:public,private,draft,scheduled',
            'video_file' => 'nullable|file|mimes:mp4,mkv,webm|max:4194304', // 4GB max
            'thumbnail' => 'nullable|image|max:5120',
            'terabox_image' => 'nullable|string|max:500',
            'previews' => 'nullable|array',
            'previews.*' => 'nullable|string|max:500',
            'preview_files' => 'nullable|array',
            'preview_files.*' => 'nullable|image|max:5120',
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('krettel')->error('[UPLOAD] Validation failed.', ['errors' => $e->errors()]);
            throw $e;
        }

        $storageProvider = $request->input('storage_provider', 'local');
        $slug = Str::slug($request->input('title')) . '-' . uniqid();
        Log::channel('krettel')->info('[UPLOAD] Validation passed. Storage provider: ' . $storageProvider, ['slug' => $slug]);

        // Check if user is linking an existing TeraBox file instead of uploading a new one
        $teraboxFilePath = trim((string) $request->input('terabox_file_path'));
        $isLinked = ($teraboxFilePath !== '');

        $stagingPath = null;
        $videoPath = null;
        if (!$isLinked && $request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $originalSize = $file->getSize() ?? 0;
            $maxAllowed = (int) ini_get('upload_max_filesize'); // e.g. "2G" -> 2 (display only)

            if ($storageProvider === 'terabox' || $storageProvider === 'pixeldrain') {
                try {
                    $stagingPath = $file->store('pending-uploads');
                    Log::channel('krettel')->info('[UPLOAD] Video staged on local disk.', [
                        'staging_path' => $stagingPath,
                        'original_name' => $file->getClientOriginalName(),
                        'file_size_bytes' => $originalSize,
                        'php_upload_max_filesize' => $maxAllowed,
                        'storage_provider' => $storageProvider,
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('krettel')->error('[UPLOAD] Failed to stage video file.', ['error' => $e->getMessage()]);
                    throw $e;
                }
            } else {
                try {
                    $videoPath = $file->store('videos', 'public');
                    Log::channel('krettel')->info('[UPLOAD] Video stored on local PUBLIC disk.', ['video_path' => $videoPath]);
                } catch (\Throwable $e) {
                    Log::channel('krettel')->error('[UPLOAD] Failed to store video to public disk.', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
        } else {
            Log::channel('krettel')->warning('[UPLOAD] No video file uploaded or file linked via TeraBox path.');
        }

        $thumbnailPath = null;
        $teraboxImage = $request->input('terabox_image');
        if ($request->hasFile('thumbnail')) {
            $thumbnailFile = $request->file('thumbnail');
            $thumbnailPath = $thumbnailFile->store('thumbnails', 'public');

            $remoteRef = PixeldrainImageStore::upload(
                $thumbnailFile,
                'thumb-' . $slug . '.' . $thumbnailFile->getClientOriginalExtension()
            );

            if ($remoteRef) {
                $teraboxImage = $remoteRef;
            }

            Log::channel('krettel')->info('[UPLOAD] Thumbnail stored.', [
                'thumbnail' => $thumbnailPath,
                'terabox_image' => $teraboxImage,
            ]);
        }

        $previews = collect($request->input('previews', []))
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->values()
            ->all() ?: [];

        if ($request->hasFile('preview_files')) {
            foreach ($request->file('preview_files') as $file) {
                if ($file) {
                    $path = $file->store('previews', 'public');
                    $previewUrl = asset('storage/' . $path);

                    $remoteRef = PixeldrainImageStore::upload(
                        $file,
                        'preview-' . $slug . '-' . uniqid() . '.' . $file->getClientOriginalExtension()
                    );

                    if ($remoteRef) {
                        $previewUrl = $remoteRef;
                    }

                    $previews[] = $previewUrl;
                }
            }
        }

        // Format remote path: make sure it has the remote prefix directory
        if ($isLinked) {
            $remotePrefix = config('terabox.remote_dir', '/Apps/Krettel');
            // If the user entered just the name like "movie.mp4", prepend the directory.
            // If they entered the full path, keep it.
            if (!str_starts_with($teraboxFilePath, '/')) {
                $teraboxFilePath = rtrim($remotePrefix, '/') . '/' . $teraboxFilePath;
            }
        }

        $video = Video::create([
            'title' => $request->input('title'),
            'slug' => $slug,
            'short_description' => $request->input('full_description'),
            'full_description' => $request->input('full_description'),
            'category_id' => $request->input('category_id'),
            'age_rating' => $request->input('age_rating'),
            'visibility' => $request->input('visibility'),
            'seo_title' => $request->input('seo_title'),
            'meta_description' => $request->input('meta_description'),
            'keywords' => $request->input('keywords'),
            'video_url' => $isLinked ? 'terabox-remote' : ($videoPath ?? 'pending-upload'),
            'storage_folder' => $isLinked ? $teraboxFilePath : null,
            'storage_provider' => $storageProvider === 'pixeldrain' ? 'pixeldrain' : ($storageProvider === 'terabox' ? 'terabox' : 'local'),
            'thumbnail' => $thumbnailPath,
            'terabox_image' => $teraboxImage,
            'previews' => !empty($previews) ? $previews : null,
            'resolution' => $request->input('resolution', '1080p'),
        ]);

        Log::channel('krettel')->info('[UPLOAD] Video row created in DB.', [
            'video_id' => $video->id,
            'title' => $video->title,
            'slug' => $video->slug,
            'video_url_status' => $video->video_url,
            'visibility' => $video->visibility,
            'category_id' => $video->category_id,
        ]);

        // Save separately uploaded audio tracks FIRST so the processing job can
        // mux them into the HLS output as extra audio renditions.
        $savedAudioCount = 0;
        if ($request->hasFile('audio_files')) {
            $languages = $request->input('audio_language', []);
            $defaults = $request->input('default_audio', []);
            foreach ($request->file('audio_files') as $index => $audioFile) {
                if (! $audioFile) {
                    continue;
                }
                $languageName = $languages[$index] ?? 'English';
                $language = \App\Models\Language::firstOrCreate(
                    ['code' => LanguageCodes::code($languageName)],
                    ['name' => $languageName]
                );
                $audioPath = $audioFile->store('audios', 'public');
                \App\Models\VideoAudioTrack::create([
                    'video_id' => $video->id,
                    'language_id' => $language->id,
                    'label' => $languageName,
                    'file_path' => $audioPath,
                    'is_default' => isset($defaults[$index]) ? true : false,
                ]);
                $savedAudioCount++;
                Log::channel('krettel')->info('[UPLOAD] Separate audio track saved for video ' . $video->id, [
                    'video_id' => $video->id,
                    'file_path' => $audioPath,
                    'language' => $languageName,
                    'is_default' => isset($defaults[$index]) ? true : false,
                ]);
            }
        }

        if ($stagingPath) {
            Log::channel('krettel')->info('[UPLOAD] Dispatching processing job for video ' . $video->id, [
                'video_id' => $video->id,
                'staging_path' => $stagingPath,
            ]);

            $absolutePath = Storage::disk('local')->path($stagingPath);
            $container = MediaProbe::container($absolutePath);
            $muxedAudio = MediaProbe::audioStreamCount($absolutePath);
            $totalAudio = $muxedAudio + $savedAudioCount;

            Log::channel('krettel')->info('[UPLOAD] Processing decision for video ' . $video->id, [
                'video_id' => $video->id,
                'container' => $container,
                'muxed_audio' => $muxedAudio,
                'separate_audio' => $savedAudioCount,
                'total_audio' => $totalAudio,
            ]);

            if ($storageProvider !== 'pixeldrain' && $totalAudio >= 2) {
                Log::channel('krettel')->info('[UPLOAD] Multi-audio source detected (' . ($container ?: 'unknown') . ', ' . $totalAudio . ' audio) — transcoding to HLS.', [
                    'video_id' => $video->id,
                ]);
                $video->update(['hls_status' => 'processing']);
                Log::channel('krettel')->info('[UPLOAD] Status -> processing (HLS transcode queued).', ['video_id' => $video->id]);
                TranscodeVideoToHls::dispatch($video, $stagingPath);
            } elseif ($storageProvider === 'pixeldrain') {
                // Pixeldrain always wins: upload the original file in the SAME
                // request (no background job), even for multi-audio sources.
                // It is streamed at its native resolution (upload 720p/1080p MP4)
                // and plays the default audio track.
                // Pixeldrain has no transcode tier — upload the original file in
                // the SAME request (no background job) and stream it at its
                // native resolution (upload 720p/1080p MP4).
                Log::channel('krettel')->info('[UPLOAD] Status -> pixeldrain (sync upload).', ['video_id' => $video->id]);

                $uploadToken = (string) $request->input('upload_token');
                $progressKey = $uploadToken !== '' ? 'pixeldrain_upload_' . $uploadToken : null;

                try {
                    @set_time_limit(0);
                    $pixeldrain = app(\App\Services\PixeldrainClient::class);

                    if ($progressKey) {
                        $pixeldrain->onProgress(function ($uploaded, $total) use ($progressKey) {
                            Cache::put($progressKey, [
                                'uploaded' => (int) $uploaded,
                                'total' => (int) $total,
                                'percent' => $total > 0 ? (int) round(($uploaded / $total) * 100) : 0,
                                'updated_at' => now()->toIso8601String(),
                            ], now()->addMinutes(30));
                        });
                    }

                    $fileId = $pixeldrain->upload($absolutePath, basename($stagingPath));

                    if ($progressKey) {
                        Cache::forget($progressKey);
                    }

                    $video->update([
                        'video_url' => 'pixeldrain-remote',
                        'storage_folder' => $fileId,
                        'storage_provider' => 'pixeldrain',
                    ]);

                    try {
                        $this->uploadAudioTracksToPixeldrain($video, $absolutePath, $pixeldrain);
                    } catch (\Throwable $e) {
                        // The video itself is already on Pixeldrain — never fail
                        // the whole upload because an extra audio track failed.
                        Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Audio track split/upload failed (video kept).', [
                            'video_id' => $video->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    try {
                        $this->uploadQualityVariantsToPixeldrain($video, $absolutePath, $pixeldrain);
                    } catch (\Throwable $e) {
                        // Same rule: variants are a bonus, never fail the upload.
                        Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Quality variant transcode/upload failed (video kept).', [
                            'video_id' => $video->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    Storage::disk('local')->delete($stagingPath);

                    Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Video uploaded synchronously.', [
                        'video_id' => $video->id,
                        'file_id' => $fileId,
                    ]);

                    \App\Models\Notification::create([
                        'user_id' => auth()->id(),
                        'title' => 'Upload Complete',
                        'message' => "'{$video->title}' is now ready to watch.",
                        'type' => 'success',
                        'link' => route('video.show', $video->slug),
                    ]);
                } catch (\Throwable $e) {
                    if ($progressKey) {
                        Cache::forget($progressKey);
                    }

                    Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Sync upload failed.', [
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                    ]);

                    $video->update(['video_url' => 'failed']);

                    \App\Models\Notification::create([
                        'user_id' => auth()->id(),
                        'title' => 'Upload Failed',
                        'message' => "'{$video->title}' failed to upload to Pixeldrain.",
                        'type' => 'error',
                        'link' => route('admin.videos.index'),
                    ]);
                }
            } else {
                Log::channel('krettel')->warning(
                    '[UPLOAD] Single-audio source (' . ($container ?: 'unknown') . ', ' . $totalAudio . ' audio) — deferring to TeraBox (no audio switcher).',
                    ['video_id' => $video->id, 'total_audio' => $totalAudio]
                );
                Log::channel('krettel')->info('[UPLOAD] Status -> terabox (TeraBox upload queued).', ['video_id' => $video->id]);
                UploadVideoToTeraBox::dispatch($video, $stagingPath);
            }
        } elseif ($isLinked) {
            Log::channel('krettel')->info('[UPLOAD] Video linked instantly from TeraBox. No sync job dispatched.', ['video_id' => $video->id]);
            // Warm the link cache
            try {
                \App\Http\Controllers\VideoController::warmStream($video);
            } catch (\Throwable $e) {}
        } else {
            Log::channel('krettel')->info('[UPLOAD] No staging path — video kept local.', ['video_id' => $video->id, 'video_url' => $video->video_url]);
        }

        // Create notification for the user
        $fileSizeMB = $request->hasFile('video_file')
            ? round($request->file('video_file')->getSize() / 1048576, 1)
            : 0;
        \App\Models\Notification::create([
            'user_id' => auth()->id(),
            'title' => $isLinked ? 'Video Linked Successfully' : 'Video Upload Started',
            'message' => $isLinked 
                ? "'{$video->title}' has been successfully mapped to TeraBox."
                : "'{$video->title}' ({$fileSizeMB} MB) is uploading" . ($stagingPath ? ' to TeraBox...' : '.'),
            'type' => $isLinked ? 'success' : 'upload',
            'link' => route('video.show', $video->slug),
        ]);

        // Process Subtitles
        if ($request->hasFile('subtitle_files')) {
            $languages = $request->input('subtitle_language', []);
            foreach ($request->file('subtitle_files') as $index => $subtitleFile) {
                if ($subtitleFile) {
                    $subtitlePath = $subtitleFile->store('subtitles', 'public');
                    \App\Models\Subtitle::create([
                        'video_id' => $video->id,
                        'language' => $languages[$index] ?? 'Unknown',
                        'file_path' => $subtitlePath,
                        'is_default' => isset($request->input('default_subtitle')[$index]) ? true : false,
                    ]);
                    Log::channel('krettel')->info('[UPLOAD] Subtitle uploaded for video ' . $video->id . '.', ['video_id' => $video->id, 'file_path' => $subtitlePath]);
                }
            }
        }

        Log::channel('krettel')->info('[UPLOAD] Upload flow completed successfully.', ['video_id' => $video->id]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully! It is now processing.',
                'redirect' => route('admin.videos.index'),
                'video_id' => $video->id,
                'slug' => $video->slug,
            ]);
        }

        return redirect()->route('admin.videos.index')->with('status', 'Video uploaded successfully! It is now processing.');
    }

    /**
     * Live status + log lines for one video. Polled by the upload popup.
     */
    public function status(Video $video)
    {
        Log::channel('krettel')->debug('[UPLOAD] Status requested for video ' . $video->id, ['video_id' => $video->id]);

        $state = 'uploading';

        if ($video->hls_status === 'processing') {
            $state = 'processing';
        } elseif ($video->hls_status === 'ready') {
            $state = 'ready';
        } elseif ($video->hls_status === 'failed') {
            $state = 'failed';
        } elseif ($video->storage_provider === 'pixeldrain' && $video->video_url === 'pixeldrain-remote') {
            $state = 'pixeldrain';
        } elseif ($video->storage_provider === 'pixeldrain' && $video->video_url === 'processing') {
            $state = 'pixeldrain-uploading';
        } elseif ($video->video_url === 'terabox-remote') {
            $state = 'terabox';
        } elseif ($video->video_url === 'processing') {
            $state = 'terabox-uploading';
        } elseif (in_array($video->video_url, ['pending-upload'], true)) {
            $state = 'pending';
        }

        return response()->json([
            'video_id' => $video->id,
            'title' => $video->title,
            'slug' => $video->slug,
            'hls_status' => $video->hls_status,
            'video_url' => $video->video_url,
            'state' => $state,
            'done' => in_array($state, ['ready', 'failed', 'terabox', 'pixeldrain'], true),
            'logs' => $this->readLogs($video->id, 120),
        ]);
    }

    /**
     * Split every audio stream out of a Pixeldrain-hosted video, upload each
     * track to Pixeldrain, and record the file IDs so the player can re-mux
     * the chosen language on demand.
     *
     * Separately uploaded audio files (already saved as local tracks) are
     * pushed to Pixeldrain too.
     */
    protected function uploadAudioTracksToPixeldrain(Video $video, string $absolutePath, \App\Services\PixeldrainClient $pixeldrain): void
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            throw new \RuntimeException('FFmpeg not configured — cannot split audio tracks.');
        }

        // 1) Muxed audio streams inside the source file.
        //    Split ALL streams in a single ffmpeg pass (one decode, no N restarts),
        //    and skip re-encoding entirely when every track is already AAC/MP3.
        $streams = MediaProbe::audioStreams($absolutePath);

        if ($streams !== []) {
            $allCopyable = collect($streams)->every(fn ($s) => in_array(strtolower($s['codec'] ?? ''), ['aac', 'mp3'], true));
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pd_audio_' . uniqid('', true);
            @mkdir($tmpDir, 0777, true);

            $processArgs = [$ffmpeg, '-y', '-nostdin', '-loglevel', 'error', '-i', $absolutePath];
            $outputFiles = [];

            foreach ($streams as $i => $stream) {
                $outFile = $tmpDir . DIRECTORY_SEPARATOR . 'audio_' . $i . '.m4a';
                $outputFiles[$i] = $outFile;

                $processArgs[] = '-map';
                $processArgs[] = '0:a:' . $i;
                if ($allCopyable) {
                    // Remux-only: near-instant, keeps original quality.
                    $processArgs[] = '-c:a';
                    $processArgs[] = 'copy';
                } else {
                    $processArgs[] = '-c:a';
                    $processArgs[] = 'aac';
                    $processArgs[] = '-b:a';
                    $processArgs[] = '160k';
                    $processArgs[] = '-ac';
                    $processArgs[] = '2';
                }
                $processArgs[] = '-f';
                $processArgs[] = 'mp4';
                $processArgs[] = $outFile;
            }

            try {
                $process = new Process($processArgs);
                $process->setTimeout(3600);
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('ffmpeg split failed: ' . trim($process->getErrorOutput()));
                }

                foreach ($streams as $i => $stream) {
                    $tmpFile = $outputFiles[$i];
                    if (! is_file($tmpFile) || filesize($tmpFile) < 1) {
                        throw new \RuntimeException('ffmpeg produced no audio output for stream ' . $i . '.');
                    }

                    $label = $stream['title']
                        ?: ($stream['language'] ? strtoupper($stream['language']) : ('Audio ' . ($i + 1)));
                    $code = $stream['language'] ?: LanguageCodes::code($label);

                    $audioId = $pixeldrain->upload($tmpFile, $label . '.m4a');

                    $language = Language::firstOrCreate(
                        ['code' => $code],
                        ['name' => ucfirst($label)]
                    );

                    VideoAudioTrack::create([
                        'video_id' => $video->id,
                        'language_id' => $language->id,
                        'label' => $label,
                        'file_path' => $audioId,
                        'storage' => 'pixeldrain',
                        'is_default' => (bool) ($stream['default'] ?? false),
                    ]);

                    Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Audio track split + uploaded.', [
                        'video_id' => $video->id,
                        'stream' => $i,
                        'label' => $label,
                        'pixeldrain_id' => $audioId,
                    ]);
                }
            } finally {
                foreach ($outputFiles as $outFile) {
                    @unlink($outFile);
                }
                @rmdir($tmpDir);
            }
        }

        // 2) Separately uploaded audio files already recorded as local tracks.
        $localTracks = VideoAudioTrack::where('video_id', $video->id)
            ->where('storage', 'local')
            ->get();

        foreach ($localTracks as $track) {
            $localPath = Storage::disk('public')->path($track->file_path);

            if (! is_file($localPath)) {
                Log::channel('krettel')->warning('[PIXELDRAIN-SYNC] Separate audio file missing, skipping.', [
                    'video_id' => $video->id,
                    'file' => $track->file_path,
                ]);
                continue;
            }

            $audioId = $pixeldrain->upload($localPath, basename($localPath));
            $oldPath = $track->file_path;

            $track->update([
                'file_path' => $audioId,
                'storage' => 'pixeldrain',
            ]);

            Storage::disk('public')->delete($oldPath);

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Separate audio uploaded to Pixeldrain.', [
                'video_id' => $video->id,
                'label' => $track->label,
                'pixeldrain_id' => $audioId,
            ]);
        }
    }

    /**
     * Transcode lower-resolution H.264 variants (720p/480p) of a Pixeldrain
     * video and upload each to Pixeldrain, so the player can offer real video
     * quality options. Each variant carries the default audio track (re-encoded
     * to AAC) and is H.264, which also guarantees browser playback even when
     * the original file is HEVC/MKV.
     *
     * Runs the encodes in parallel (multicore) and never fails the main
     * upload — any failed variant is logged and skipped. While ffmpeg runs,
     * live per-variant percent is written into the sync progress cache so the
     * popup can show real transcoding progress instead of a frozen 100%.
     */
    protected function uploadQualityVariantsToPixeldrain(Video $video, string $absolutePath, \App\Services\PixeldrainClient $pixeldrain, ?string $progressKey = null): void
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            return;
        }

        $probe = MediaProbe::streams($absolutePath);
        if (! is_array($probe) || $probe === []) {
            return;
        }

        $videoHeight = 0;
        $defaultAudioIndex = null;
        $audioIdx = 0;

        foreach ($probe as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && $videoHeight === 0) {
                $videoHeight = (int) ($stream['height'] ?? 0);
            }
            if (($stream['codec_type'] ?? null) === 'audio') {
                if ($defaultAudioIndex === null && ($stream['disposition']['default'] ?? 0) === 1) {
                    $defaultAudioIndex = $audioIdx;
                }
                $audioIdx++;
            }
        }

        if ($videoHeight < 1) {
            return;
        }

        $targets = array_filter([720, 480], fn ($h) => $h < $videoHeight);
        if ($targets === []) {
            return;
        }

        $format = MediaProbe::format($absolutePath);
        $durationUs = (int) round(((float) ($format['duration'] ?? 0)) * 1000000);

        $jobs = [];
        foreach ($targets as $height) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'pd_q_') . '.mp4';

            $args = [
                $ffmpeg, '-y', '-nostdin', '-loglevel', 'error',
                '-progress', 'pipe:1',
                '-i', $absolutePath,
                '-map', '0:v:0',
                '-vf', 'scale=-2:' . $height,
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '26',
                '-movflags', '+faststart',
            ];

            if ($defaultAudioIndex !== null) {
                $args[] = '-map';
                $args[] = '0:a:' . $defaultAudioIndex;
                $args[] = '-c:a';
                $args[] = 'aac';
                $args[] = '-b:a';
                $args[] = '160k';
                $args[] = '-ac';
                $args[] = '2';
            } else {
                $args[] = '-an';
            }

            $args[] = '-f';
            $args[] = 'mp4';
            $args[] = $tmpFile;

            $process = new Process($args);
            $process->setTimeout(3600);
            $process->start();

            $jobs[] = [
                'height' => $height,
                'tmpFile' => $tmpFile,
                'process' => $process,
                'percent' => 0,
                'outTimeUs' => 0,
            ];
        }

        // Drain ffmpeg -progress output while the encodes run and mirror live
        // per-variant percent into the popup cache.
        $finished = 0;
        while ($finished < count($jobs)) {
            $finished = 0;
            foreach ($jobs as $idx => $job) {
                $process = $job['process'];
                $chunk = $process->getIncrementalOutput();
                if ($chunk !== '') {
                    $jobs[$idx]['outTimeUs'] = $this->parseFfmpegProgressUs($chunk, $job['outTimeUs']);
                }
                if (! $process->isRunning()) {
                    $jobs[$idx]['percent'] = 100;
                    $finished++;
                } elseif ($durationUs > 0 && $jobs[$idx]['outTimeUs'] > 0) {
                    $jobs[$idx]['percent'] = min(99, (int) (($jobs[$idx]['outTimeUs'] / $durationUs) * 100));
                }
            }
            $this->writeVariantProgress($progressKey, $jobs);
            usleep(300000);
        }

        foreach ($jobs as $job) {
            /** @var \Symfony\Component\Process\Process $process */
            $process = $job['process'];
            $tmpFile = $job['tmpFile'];
            $height = $job['height'];

            try {
                $process->wait();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('ffmpeg variant transcode failed: ' . trim($process->getErrorOutput()));
                }
                if (! is_file($tmpFile) || filesize($tmpFile) < 1) {
                    throw new \RuntimeException('ffmpeg produced no output for ' . $height . 'p.');
                }

                $label = $height . 'p';

                if ($progressKey) {
                    Cache::put($progressKey, [
                        'uploaded' => 0,
                        'total' => 0,
                        'percent' => 100,
                        'phase' => 'Uploading ' . $label . ' variant…',
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addMinutes(90));
                }

                $fileId = $pixeldrain->upload($tmpFile, $label . '.mp4');

                \App\Models\VideoQualityVariant::create([
                    'video_id' => $video->id,
                    'file_path' => $fileId,
                    'label' => $label,
                    'height' => $height,
                    'storage' => 'pixeldrain',
                    'is_default' => false,
                ]);

                Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Quality variant transcoded + uploaded.', [
                    'video_id' => $video->id,
                    'quality' => $label,
                    'pixeldrain_id' => $fileId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[PIXELDRAIN-SYNC] Quality variant skipped (' . $height . 'p) for video ' . $video->id . ': ' . $e->getMessage());
            } finally {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Extract the latest out_time_us from a chunk of ffmpeg -progress output.
     */
    protected function parseFfmpegProgressUs(string $chunk, int $current): int
    {
        foreach (preg_split('/\R/', $chunk) as $line) {
            $line = trim($line);
            if (preg_match('/^out_time_us=(\d+)$/', $line, $m)) {
                $current = (int) $m[1];
            }
        }

        return $current;
    }

    /**
     * Write a human-readable combined transcoding progress string into the
     * sync progress cache (e.g. "Transcoding variants — 720p 45%, 480p 60%").
     */
    protected function writeVariantProgress(?string $progressKey, array $jobs): void
    {
        if (! $progressKey) {
            return;
        }

        $parts = [];
        $allDone = true;
        foreach ($jobs as $job) {
            $parts[] = $job['height'] . 'p ' . $job['percent'] . '%';
            if ($job['percent'] < 100) {
                $allDone = false;
            }
        }

        if ($allDone) {
            return;
        }

        Cache::put($progressKey, [
            'uploaded' => 0,
            'total' => 0,
            'percent' => 100,
            'phase' => 'Transcoding variants — ' . implode(', ', $parts),
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(90));
    }

    /**
     * Live byte-progress of an in-flight Pixeldrain sync upload.
     *
     * The sync upload runs inside the store() request, so the popup cannot
     * know the video id yet (it is only returned when store() finishes).
     * Instead the popup sends a random token with the form and polls this
     * endpoint, which just reads the progress out of cache. It is registered
     * WITHOUT the session middleware so it does not block on the session lock
     * that store() holds while it uploads.
     */
    public function progress(string $token)
    {
        $data = Cache::get('pixeldrain_upload_' . $token);

        if (! is_array($data)) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'uploaded' => $data['uploaded'] ?? 0,
            'total' => $data['total'] ?? 0,
            'percent' => $data['percent'] ?? 0,
            'phase' => $data['phase'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ]);
    }

    /**
     * Keep the sync progress entry alive after the raw upload hits 100% so the
     * popup can report the post-upload stages (audio split / variant transcode)
     * instead of sitting frozen at 100%.
     */
    protected function setSyncPhase(?string $progressKey, string $phase): void
    {
        if (! $progressKey) {
            return;
        }

        Cache::put($progressKey, [
            'uploaded' => 0,
            'total' => 0,
            'percent' => 100,
            'phase' => $phase,
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(90));
    }

    /**
     * Tail today's krettel log file for lines mentioning this video id.
     */
    protected function readLogs(int $videoId, int $limit = 120): array
    {
        $files = [
            storage_path('logs/krettel-' . now()->format('Y-m-d') . '.log'),
            storage_path('logs/krettel.log'),
        ];

        $needle = '"video_id":' . $videoId;
        $lines = [];

        foreach ($files as $file) {
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }

            $fh = @fopen($file, 'r');
            if (! $fh) {
                continue;
            }

            while (($line = fgets($fh)) !== false) {
                if (str_contains($line, $needle)) {
                    $lines[] = $this->parseLogLine($line);
                }
            }
            fclose($fh);
        }

        return array_slice($lines, -$limit);
    }

    protected function parseLogLine(string $line): array
    {
        $time = '';
        $level = 'info';
        $message = trim($line);

        if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s?(.*)$/', $line, $m)) {
            $time = $m[1];
            $level = strtolower($m[3]);
            $message = trim($m[4]);
        }

        return ['time' => $time, 'level' => $level, 'message' => $message];
    }
}
