<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use App\Jobs\TranscodeVideoToHls;
use App\Jobs\UploadVideoToTeraBox;
use App\Support\LanguageCodes;
use App\Support\MediaProbe;
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
        Log::info('[UPLOAD] Upload request received.', [
            'title' => $request->input('title'),
            'storage_provider' => $request->input('storage_provider', 'local'),
            'has_video_file' => $request->hasFile('video_file'),
            'has_thumbnail' => $request->hasFile('thumbnail'),
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
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('[UPLOAD] Validation failed.', ['errors' => $e->errors()]);
            throw $e;
        }

        $storageProvider = $request->input('storage_provider', 'local');
        $slug = Str::slug($request->input('title')) . '-' . uniqid();
        Log::info('[UPLOAD] Validation passed. Storage provider: ' . $storageProvider, ['slug' => $slug]);

        $stagingPath = null;
        $videoPath = null;
        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $originalSize = $file->getSize() ?? 0;
            $maxAllowed = (int) ini_get('upload_max_filesize'); // e.g. "2G" -> 2 (display only)

            if ($storageProvider === 'terabox') {
                try {
                    $stagingPath = $file->store('pending-uploads');
                    Log::info('[UPLOAD] Video staged on local disk.', [
                        'staging_path' => $stagingPath,
                        'original_name' => $file->getClientOriginalName(),
                        'file_size_bytes' => $originalSize,
                        'php_upload_max_filesize' => $maxAllowed,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[UPLOAD] Failed to stage video file.', ['error' => $e->getMessage()]);
                    throw $e;
                }
            } else {
                try {
                    $videoPath = $file->store('videos', 'public');
                    Log::info('[UPLOAD] Video stored on local PUBLIC disk.', ['video_path' => $videoPath]);
                } catch (\Throwable $e) {
                    Log::error('[UPLOAD] Failed to store video to public disk.', ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
        } else {
            Log::warning('[UPLOAD] No video file provided in request.');
        }

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
            Log::info('[UPLOAD] Thumbnail stored on public disk.', ['thumbnail' => $thumbnailPath]);
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
            'video_url' => $videoPath ?? 'pending-upload',
            'thumbnail' => $thumbnailPath,
            'terabox_image' => $request->input('terabox_image'),
            'previews' => collect($request->input('previews', []))
                ->filter(fn ($url) => is_string($url) && trim($url) !== '')
                ->values()
                ->all() ?: null,
            'resolution' => $request->input('resolution', '1080p'),
        ]);

        Log::info('[UPLOAD] Video row created in DB.', [
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
                    'file_path' => $audioPath,
                    'language' => $languageName,
                    'is_default' => isset($defaults[$index]) ? true : false,
                ]);
            }
        }

        if ($stagingPath) {
            Log::info('[UPLOAD] Dispatching processing job.', [
                'video_id' => $video->id,
                'staging_path' => $stagingPath,
            ]);

            $muxedAudio = MediaProbe::audioStreamCount(
                Storage::disk('local')->path($stagingPath)
            );
            $totalAudio = $muxedAudio + $savedAudioCount;

            Log::channel('krettel')->info('[UPLOAD] Processing decision for video ' . $video->id, [
                'muxed_audio' => $muxedAudio,
                'separate_audio' => $savedAudioCount,
                'total_audio' => $totalAudio,
            ]);

            if ($totalAudio >= 2) {
                Log::channel('krettel')->info('[UPLOAD] Multi-audio source detected — transcoding to HLS.', [
                    'video_id' => $video->id,
                ]);
                $video->update(['hls_status' => 'processing']);
                TranscodeVideoToHls::dispatch($video, $stagingPath);
            } else {
                Log::channel('krettel')->warning(
                    '[UPLOAD] Fewer than 2 audio sources — deferring to TeraBox (no audio switcher).',
                    ['video_id' => $video->id, 'total_audio' => $totalAudio]
                );
                UploadVideoToTeraBox::dispatch($video, $stagingPath);
            }
        } else {
            Log::info('[UPLOAD] No staging path — video kept local (video_url=' . $video->video_url . ').');
        }

        // Create notification for the user
        $fileSizeMB = $request->hasFile('video_file')
            ? round($request->file('video_file')->getSize() / 1048576, 1)
            : 0;
        \App\Models\Notification::create([
            'user_id' => auth()->id(),
            'title' => 'Video Upload Started',
            'message' => "'{$video->title}' ({$fileSizeMB} MB) is uploading" . ($stagingPath ? ' to TeraBox...' : '.'),
            'type' => 'upload',
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
                    Log::info('[UPLOAD] Subtitle uploaded for video ' . $video->id . '.', ['file_path' => $subtitlePath]);
                }
            }
        }

        Log::info('[UPLOAD] Upload flow completed successfully.', ['video_id' => $video->id]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully! It is now processing.',
                'redirect' => route('admin.videos.index')
            ]);
        }

        return redirect()->route('admin.videos.index')->with('status', 'Video uploaded successfully! It is now processing.');
    }
}
