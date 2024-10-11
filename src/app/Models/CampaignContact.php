<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;
class CampaignContact extends Model
{
    use HasFactory;
    protected $guarded = [];


      /**
       * get campaign contact
       */
      public function contactx(){
        return $this->belongsTo(Contact::class, 'contact_uid', 'uid');
      }

}
