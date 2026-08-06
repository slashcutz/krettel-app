<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\User;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $topVideos = Video::orderByDesc('views')->take(10)->get();
        $recentUsers = User::latest()->take(10)->get();
        
        $totalWatchTimeSeconds = \App\Models\VideoAnalytic::sum('watch_time_seconds');
        $totalWatchTimeHours = round($totalWatchTimeSeconds / 3600, 1);
        
        $deviceAnalytics = \App\Models\VideoAnalytic::select('device_type', \DB::raw('sum(watch_time_seconds) as total_seconds'), \DB::raw('count(id) as session_count'))
            ->groupBy('device_type')
            ->get();

        return view('admin.reports.index', compact('topVideos', 'recentUsers', 'totalWatchTimeHours', 'deviceAnalytics'));
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
