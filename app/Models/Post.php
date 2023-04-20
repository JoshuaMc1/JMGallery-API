<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'user_id',
        'slug',
        'image_path',
        'image',
        'description',
        'nsfw',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'image_path',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likedPosts()
    {
        return $this->hasMany(LikedPost::class);
    }
}
