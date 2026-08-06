<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\WatchHistory;
use App\Support\DeviceContext;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        $favorites = Favorite::with('video')
            ->where(fn ($q) => DeviceContext::contextQuery($q))
            ->latest()
            ->get()
            ->filter(fn ($f) => $f->video && $f->video->visibility === 'public');

        $watchHistory = WatchHistory::with('video')
            ->where(fn ($q) => DeviceContext::contextQuery($q))
            ->latest('updated_at')
            ->take(10)
            ->get()
            ->filter(fn ($h) => $h->video && $h->video->visibility === 'public')
            ->unique('video_id');

        $guest = ! auth()->check();
        $deviceType = DeviceContext::type();

        return view('frontend.my-list.index', compact('favorites', 'watchHistory', 'guest', 'deviceType'));
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
        ]);

        $scope = DeviceContext::scope();

        $query = Favorite::where('video_id', $request->video_id);

        if ($scope['user_id']) {
            $query->where('user_id', $scope['user_id']);
        } else {
            $query->whereNull('user_id')->where('device_id', $scope['device_id']);
        }

        $favorite = $query->first();

        if ($favorite) {
            $favorite->delete();
            $added = false;
        } else {
            Favorite::create($scope + ['video_id' => $request->video_id]);
            $added = true;
        }

        $count = Favorite::where(fn ($q) => DeviceContext::contextQuery($q))->count();

        return response()->json([
            'added' => $added,
            'count' => $count,
            'device' => $scope['device_type'],
        ]);
    }
}
