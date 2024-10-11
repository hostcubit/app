<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory, Notifiable, Filterable;

    protected $fillable = [
        'uid',
        'user_id',
        'group_id',
        'meta_data',
        'whatsapp_contact',
        'email_contact',
        'sms_contact',
        'last_name',
        'first_name',
        'status'
    ];

    protected $casts = [
        "meta_data" => "object"
    ];

    protected static function booted()
    {
        static::creating(function ($contact) {
            
            $contact->uid    = str_unique();
            $contact->status = StatusEnum::TRUE->status();
        });
    }

    public function group()
    {
    	return $this->belongsTo(Group::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', StatusEnum::TRUE->status());
    }

    public function scopeInactive($query)
    {
        return $query->where('status', StatusEnum::FALSE->status());
    }
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
