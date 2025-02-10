<?php

namespace App\Models\Administrative\Mobile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MobileContent extends Model
{
    protected $table        = 'c_mobile_contents';
    protected $primaryKey   = 'mobile_content_id';
    protected $fillable     = ['mobile_content_name', 'mobile_content_text', 'mobile_subject', 'mobile_change', 'created_by', 'created_host'];
    protected $casts        = ['mobile_change' => 'array'];

    public static function rules ($id = null)
    {
        return [
            
        ];
    }
}
