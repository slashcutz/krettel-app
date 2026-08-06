<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoCategory extends Model
{
    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(VideoCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(VideoCategory::class, 'parent_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'category_id');
    }
}