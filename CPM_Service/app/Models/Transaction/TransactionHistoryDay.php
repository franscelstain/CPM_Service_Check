<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TransactionHistoryDay extends Model 
{    
    protected $table        = 't_trans_histories_days';
    protected $primaryKey   = 'trans_history_day_id';
    protected $guarded      = ['trans_history_day_id', 'created_at', 'updated_at'];
    
    public static function rules()
    {
        return [
           
        ];
    }
}