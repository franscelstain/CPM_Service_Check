<?php

namespace App\Models\SA\Reference\KYC;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Region extends Model
{
    protected $table        = 'm_regions';
    protected $primaryKey   = 'region_id';
    protected $fillable     = ['region_name', 'region_code', 'description', 'parent_code', 'postal_code', 'region_type', 'created_by', 'is_data', 'created_host'];
    
    public static function rules($id = null)
    {
        return [
            'region_code'   => ['required', Rule::unique('m_regions')->ignore($id, 'region_id')],
            'region_name'   => 'required|max:50',
            'region_type'   => 'required',
            'description'   => 'max:225',
            'parent_code'   => 'required_if:region_type,Kota / Kab.|required_if:region_type,Kecamatan|required_if:region_type,Kelurahan'
        ];
    }
}