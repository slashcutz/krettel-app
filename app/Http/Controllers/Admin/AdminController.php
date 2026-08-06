<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Video;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'users_count' => User::count(),
            'videos_count' => Video::count(),
            'views_count' => Video::sum('views'),
            'today_uploads' => Video::whereDate('created_at', today())->count(),
        ];
        
        $recentVideos = Video::with('category')->latest()->take(5)->get();
        $activityLogs = \App\Models\ActivityLog::latest()->take(3)->get();

        return view('admin.dashboard', compact('stats', 'recentVideos', 'activityLogs'));
    }

    public function pendingNotifications()
    {
        // In-flight + recently finished uploads — mirrors the upload popup's live states.
        $pendingVideos = Video::latest()
            ->where(function ($q) {
                $q->whereIn('video_url', ['pending-upload', 'processing', 'failed', 'terabox-remote', 'hls-local'])
                  ->orWhere('hls_status', 'processing');
            })
            ->take(20)
            ->get()
            ->map(function ($video) {
                $progress = \Illuminate\Support\Facades\Cache::get('terabox_upload_' . $video->id);
                $state = $this->stateFor($video);

                return [
                    'id' => $video->id,
                    'title' => \Illuminate\Support\Str::limit($video->title, 40),
                    'uploaded_human' => $video->created_at->diffForHumans(),
                    'status' => $video->video_url,
                    'state' => $state,
                    'done' => in_array($state, ['ready', 'terabox', 'failed'], true),
                    'phase' => $progress['phase'] ?? null,
                    'size_mb' => isset($progress['total']) ? round($progress['total'] / 1048576, 1) : null,
                    'uploaded_mb' => isset($progress['bytes']) ? round($progress['bytes'] / 1048576, 1) : null,
                    'progress' => isset($progress['total']) && $progress['total'] > 0
                        ? min(100, (int) round($progress['bytes'] / $progress['total'] * 100))
                        : null,
                ];
            });

        return response()->json([
            'pending_count' => $pendingVideos->where('done', false)->count(),
            'terabox_expired' => \App\Services\TeraBoxClient::sessionExpired(),
            'videos' => $pendingVideos->values(),
        ]);
    }

    /**
     * Map a video to the same live states the upload popup shows.
     */
    protected function stateFor(Video $video): string
    {
        if ($video->hls_status === 'processing') {
            return 'processing';
        }
        if ($video->hls_status === 'ready' || $video->video_url === 'hls-local') {
            return 'ready';
        }
        if ($video->hls_status === 'failed' || $video->video_url === 'failed') {
            return 'failed';
        }
        if ($video->video_url === 'terabox-remote') {
            return 'terabox';
        }
        if ($video->video_url === 'processing') {
            return 'terabox-uploading';
        }
        if ($video->video_url === 'pending-upload') {
            return 'pending';
        }

        return $video->video_url;
    }
}
