<?php

namespace App\Models\SA\Campaign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Reference extends Model    
{
    protected $table        = 'm_campaign_references';
    protected $primaryKey   = 'campaign_ref_id'; 
    protected $guarded      = [ 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];

    public static function rules($id = null)
    {
    	return [
            'campaign_ref_name' => ['required', Rule::unique('m_campaign_references')->ignore($id, 'campaign_ref_id')->where(function ($query) {
                                        return $query->where('is_active', 'Yes');
                                   })],
            'campaign_ref_type' => 'required'
    	];
    }
}
