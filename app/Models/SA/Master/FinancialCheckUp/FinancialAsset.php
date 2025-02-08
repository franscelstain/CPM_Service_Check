<?php

namespace App\Models\SA\Master\FinancialCheckUp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class FinancialAsset extends Model
{
    protected $table        = 'm_financials_assets';
    protected $primaryKey   = 'financial_asset_id';
    protected $guarded     = ['financial_asset_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
        return [
            
        ];
    }
}
