<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TransactionHistory extends Model 
{    
    protected $table        = 't_trans_histories';
    protected $primaryKey   = 'trans_history_id';
    protected $guarded      = ['trans_history_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'investor_id'           => 'required', 
            'product_id'            => 'required', 
            'trans_reference_id'    => 'required', 
            'type_reference_id'     => 'required', 
            'transaction_date'      => 'required'
        ];
    }
}