<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PlaylistItem::class);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'playlist_items');
    }
}