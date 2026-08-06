<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VideoAnalytic;

class AnalyticsController extends Controller
{
    public function logWatchTime(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'watch_time' => 'required|integer',
        ]);

        $userAgent = $request->header('User-Agent');
        $deviceType = 'desktop'; // default
        
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/smart-tv|smarttv|appletv|roku|firetv|chromecast/i', $userAgent)) {
            $deviceType = 'tv';
        }

        $analytic = VideoAnalytic::firstOrCreate([
            'video_id' => $request->video_id,
            'user_id' => auth()->id(),
            'device_type' => $deviceType,
            'ip_address' => $request->ip(),
        ]);

        $analytic->increment('watch_time_seconds', $request->watch_time);

        return response()->json(['status' => 'success', 'device' => $deviceType]);
    }
}
