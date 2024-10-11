<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'user_id',
        'name',
        'status',
    ];

    protected static function booted()
    {
        static::creating(function ($group) {

            $group->uid    = str_unique();
            $group->status = StatusEnum::TRUE->status();
        });
    }

    public function user()
    {
    	return $this->belongsTo(User::class, 'user_id');
    }
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'group_id');
    }
    public function scopeActive($query)
    {
        return $query->where('status', StatusEnum::TRUE->status());
    }

    public function scopeInactive($query)
    {
        return $query->where('status', StatusEnum::FALSE->status());
    }

    public function getRelationships()
    {
        return ['contacts'];
    }
}
