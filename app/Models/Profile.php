<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'profile',
        'description',
        'user_id',
    ];

    protected $hidden = [
        'id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
