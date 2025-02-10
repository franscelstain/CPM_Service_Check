<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SalesBranch extends Model
{
    protected $table        = 'u_sales_branch';
    protected $primaryKey   = 'branch_id';
    protected $guarded      = ['branch_id', 'is_active', 'created_at', 'updated_at', 'updated_by', 'updated_host'];
	
	public static function rules($id = null)
	{
		return [
            
		];
	}
}
