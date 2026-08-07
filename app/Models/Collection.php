<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $guarded = [];

    public function imageUrl(): ?string
    {
        $value = $this->terabox_image ?: $this->image;

        if (empty($value)) {
            return null;
        }

        return \App\Support\TeraBoxImage::url($value, 'collection', $this->id);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CollectionItem::class);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'collection_items');
    }
}
