<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Jobs\TranscodeVideoToHls;
use App\Jobs\UploadVideoToTeraBox;
use App\Support\LanguageCodes;
use App\Support\MediaProbe;
use App\Support\TeraBoxImageStore;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            if ($storageProvider === 'terabox') {
                try {
                    $stagingPath = $file->store('pending-uploads');
                    Log::channel('krettel')->info('[UPLOAD] Video staged on local disk.', [
                        'staging_path' => $stagingPath,
                        'original_name' => $file->getClientOriginalName(),
                        'file_size_bytes' => $originalSize,
                        'php_upload_max_filesize' => $maxAllowed,
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

            $teraboxRef = TeraBoxImageStore::upload(
                $thumbnailFile,
                TeraBoxImageStore::remoteDir('VideoThumbnails'),
                'thumb-' . $slug . '.' . $thumbnailFile->getClientOriginalExtension()
            );

            if ($teraboxRef) {
                $teraboxImage = $teraboxRef;
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
                    $previews[] = asset('storage/' . $path);
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

            if ($totalAudio >= 2) {
                Log::channel('krettel')->info('[UPLOAD] Multi-audio source detected (' . ($container ?: 'unknown') . ', ' . $totalAudio . ' audio) — transcoding to HLS.', [
                    'video_id' => $video->id,
                ]);
                $video->update(['hls_status' => 'processing']);
                Log::channel('krettel')->info('[UPLOAD] Status -> processing (HLS transcode queued).', ['video_id' => $video->id]);
                TranscodeVideoToHls::dispatch($video, $stagingPath);
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
            'done' => in_array($state, ['ready', 'failed', 'terabox'], true),
            'logs' => $this->readLogs($video->id, 120),
        ]);
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
