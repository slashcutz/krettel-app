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
        $pendingVideos = Video::whereIn('video_url', ['pending-upload', 'processing', 'failed'])
            ->latest()->take(20)->get();

        return response()->json([
            'pending_count' => $pendingVideos->count(),
            'terabox_expired' => \App\Services\TeraBoxClient::sessionExpired(),
            'videos' => $pendingVideos->map(function ($video) {
                $progress = \Illuminate\Support\Facades\Cache::get('terabox_upload_' . $video->id);

                return [
                    'id' => $video->id,
                    'title' => \Illuminate\Support\Str::limit($video->title, 40),
                    'uploaded_human' => $video->created_at->diffForHumans(),
                    'status' => $video->video_url,
                    'phase' => $progress['phase'] ?? null,
                    'size_mb' => isset($progress['total']) ? round($progress['total'] / 1048576, 1) : null,
                    'uploaded_mb' => isset($progress['bytes']) ? round($progress['bytes'] / 1048576, 1) : null,
                    'progress' => isset($progress['total']) && $progress['total'] > 0
                        ? min(100, (int) round($progress['bytes'] / $progress['total'] * 100))
                        : null,
                ];
            }),
        ]);
    }
}
