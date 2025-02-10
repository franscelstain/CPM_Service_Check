<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Allocation extends Model
{
    protected $table        = 'm_portfolio_allocations';
    protected $primaryKey   = 'allocation_id';
    protected $fillable     = ['model_id', 'asset_class_id', 'description', 'created_by', 'created_host'];
    
    public static function rules($id = null,$request)
    {
        return [
            'model_id'          => ['required', Rule::unique('m_portfolio_allocations')->ignore($id, 'allocation_id')->where(function ($query) use($request) {
                                        return $query->where([['is_active', 'Yes'], ['asset_class_id', $request->input('asset_class_id')]]);
                                   })],
            'asset_class_id'    => 'required'
            
        ];
    }
}
