<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAudioTrack extends Model
{
    protected $guarded = [];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}