<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TransactionOtp extends Model 
{    
    protected $table        = 't_trans_histories_otp';
    protected $primaryKey   = 'trans_history_otp_id';
    protected $guarded      = ['trans_history_otp_id',  'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
        ];
    }
}