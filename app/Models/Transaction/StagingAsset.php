<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class StagingAsset extends Model 
{
    const UPDATED_AT = null;
    protected $table = 't_stg_assets';
    protected $guarded = ['id', 'created_at'];
}