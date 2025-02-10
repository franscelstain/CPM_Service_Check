<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Models\Users\Category;
use Illuminate\Http\Request;

class CategoriesController extends AppController
{
    public $table = 'Users\Category';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }

    public function getcategory(Request $request)
    {
        try
        {
            $data   = Category::select()
                    ->where('is_active', ['Yes'])
                    ->whereNotIn('usercategory_name', ['Investor'])->get();
            return $this->app_response('category', $data);            
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}