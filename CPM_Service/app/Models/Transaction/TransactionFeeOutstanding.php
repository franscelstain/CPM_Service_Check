<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TransactionFeeOutstanding extends Model 
{    
    protected $table        = 't_fee_outstanding';
    protected $primaryKey   = 'fee_outstanding_id';
    protected $guarded      = ['fee_outstanding_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
        ];
    }
}