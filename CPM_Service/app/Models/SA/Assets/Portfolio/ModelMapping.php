<?php

namespace App\Models\SA\Assets\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ModelMapping extends Model
{
    protected $table        = 'm_models_mapping';
    protected $primaryKey   = 'model_mapping_id';
    protected $fillable     = ['model_id', 'profile_id', 'model_mapping_name', 'description', 'created_by', 'created_host'];
    
    public static function rules($id = null, $request)
    {
        return [
            'model_id'              => ['required', Rule::unique('m_models_mapping')->ignore($id, 'model_mapping_id')->where(function ($query) use ($request) {
                                            return $query->where([['is_active', 'Yes'], ['profile_id', $request->input('profile_id')]]);
                                       })],
            'profile_id'            => 'required',
            'model_mapping_name'    => 'required',
            'description'           => 'max:160' 
        ];
    }
}
