<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class WatchListInvestor extends Model 
{    
    protected $table        = 't_watchlist_investors';
    protected $primaryKey   = 'watchlist_investor_id';
    protected $guarded      = ['watchlist_investor_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            
        ];
    }
}