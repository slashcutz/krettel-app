<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;

class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $videos = Video::with('category')->latest()->paginate(15);
        return view('admin.videos.index', compact('videos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $video = Video::with('category')->findOrFail($id);
        $categories = \App\Models\VideoCategory::orderBy('name')->get();

        return view('admin.videos.edit', compact('video', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $video = Video::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:videos,slug,' . $id,
            'short_description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'category_id' => 'nullable|exists:video_categories,id',
            'release_date' => 'nullable|date',
            'age_rating' => 'nullable|string|max:20',
            'video_type' => 'nullable|string|max:50',
            'resolution' => 'nullable|string|max:50',
            'quality' => 'nullable|string|max:50',
            'visibility' => 'required|in:public,private,unlisted',
            'thumbnail' => 'nullable|string|max:500',
            'thumbnail_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'poster' => 'nullable|string|max:500',
            'poster_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'trailer_url' => 'nullable|url|max:500',
            'terabox_image' => 'nullable|string|max:500',
            'storage_folder' => 'nullable|string|max:500',
            'video_file' => 'nullable|file|mimes:mp4,mkv,webm|max:4194304',
            'previews' => 'nullable|array',
            'previews.*' => 'nullable|string|max:500',
            'preview_files' => 'nullable|array',
            'preview_files.*' => 'nullable|image|max:5120',
        ]);

        $updateData = $request->only([
            'title', 'slug', 'short_description', 'full_description', 'category_id',
            'release_date', 'age_rating', 'video_type', 'resolution', 'quality',
            'visibility', 'thumbnail', 'poster', 'trailer_url', 'terabox_image',
            'storage_folder',
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('thumbnails', 'public');
            $updateData['thumbnail'] = asset('storage/' . $path);
        }

        if ($request->hasFile('poster_file')) {
            $path = $request->file('poster_file')->store('posters', 'public');
            $updateData['poster'] = asset('storage/' . $path);
        }

        // Handle TeraBox Remote path mapping
        if (!empty($updateData['storage_folder'])) {
            $remotePrefix = config('terabox.remote_dir', '/Apps/Krettel');
            $path = trim($updateData['storage_folder']);
            if (!str_starts_with($path, '/')) {
                $path = rtrim($remotePrefix, '/') . '/' . $path;
            }
            $updateData['storage_folder'] = $path;
            $updateData['video_url'] = 'terabox-remote';
        }

        $video->update($updateData);

        // Handle replacing local video file
        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $stagingPath = $file->store('pending-uploads');
            $video->update(['video_url' => 'processing']);
            \App\Jobs\UploadVideoToTeraBox::dispatch($video, $stagingPath);
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

        $video->previews = !empty($previews) ? $previews : null;
        $video->save();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $video = Video::findOrFail($id);

        // If the video is stored on TeraBox, remove the remote file too.
        if ($video->video_url === 'terabox-remote' && $video->storage_folder) {
            try {
                $terabox = app(\App\Services\TeraBoxClient::class);
                $terabox->deleteFile($video->storage_folder);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[TERABOX-DELETE] Failed to delete remote file.', [
                    'video_id' => $video->id,
                    'remote_path' => $video->storage_folder,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        \App\Models\VideoAudioTrack::where('video_id', $video->id)->delete();
        \App\Models\Subtitle::where('video_id', $video->id)->delete();
        \App\Models\VideoAnalytic::where('video_id', $video->id)->delete();
        \App\Models\WatchHistory::where('video_id', $video->id)->delete();
        \App\Models\Favorite::where('video_id', $video->id)->delete();

        $video->delete();

        return redirect()->route('admin.videos.index')
            ->with('success', 'Video deleted successfully.');
    }
}
