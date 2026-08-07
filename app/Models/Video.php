<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $guarded = [];

    protected $casts = [
        'previews' => 'array',
    ];

    public function resolveImageUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return \App\Support\TeraBoxImage::url($value, 'video', $this->id);
    }

    public function category()
    {
        return $this->belongsTo(VideoCategory::class, 'category_id');
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'genre_video');
    }

    public function subtitles()
    {
        return $this->hasMany(Subtitle::class);
    }
}
