<?php

namespace App\Models\Investor\Financial\Condition;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class IncomeExpense extends Model 
{    
    protected $table        = 't_income_expense';
    protected $primaryKey   = 'transaction_id';
    protected $guarded      = ['transaction_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
    
    public static function rules()
    {
        return [
            'financial_id'      => 'required',
            'transaction_name'  => 'required',
            'period_of_time'    => 'required|string',
            'amount'            => 'required|numeric'
        ];
    }
}