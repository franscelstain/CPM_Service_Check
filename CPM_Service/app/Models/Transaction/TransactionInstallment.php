<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class TransactionInstallment extends Model 
{    
    protected $table        = 't_installments';
    protected $primaryKey   = 'trans_installment_id';
    protected $guarded      = ['trans_installment_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'investor_id'           => 'required', 
            'product_id'            => 'required'
        ];
    }
}