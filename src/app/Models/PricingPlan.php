<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Traits\Filterable;

class PricingPlan extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'name',
        'type',
        'description',
        'amount',
        'sms', 
        'email', 
        'whatsapp', 
        'duration',
        'status',
        'carry_forward',
        'recommended_status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        
        'sms' => 'object',
        'email' => 'object',
        'whatsapp' => 'object',
    ];

    public static function columnExists($columnName)
    {
        $table = (new static)->getTable();
        $columnExists = Schema::hasColumn($table, $columnName);

        return $columnExists;
    }

    protected static function booted()
    {
        static::creating(function ($plan) {
            
            $plan->status = StatusEnum::TRUE->status();
        });
    }
    
}
