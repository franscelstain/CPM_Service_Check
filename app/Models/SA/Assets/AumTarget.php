<?php

namespace App\Models\SA\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AumTarget extends Model
{
    protected $table        = 'm_aum_target';
    protected $primaryKey   = 'id_aum_target';
    protected $fillable     = ['effective_date', 'target_aum', 'asset_category', 'created_by', 'created_host'];
	protected $casts        = ['asset_category' => 'array'];
	
	public static function rules($id = null)
	{
		return [
            'effective_date'    => 'required|date_format:Y-m-d',
            'target_aum'        => 'required|numeric|min:1',
            'asset_category'    => 'required'
		];
	}
}
