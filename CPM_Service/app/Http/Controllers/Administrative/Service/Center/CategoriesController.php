<?php

namespace App\Http\Controllers\Administrative\Service\Center;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class CategoriesController extends AppController
{
    public $table = 'Administrative\Service\Center\Category';

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
        return $this->db_save($request, $id, ['path' => 'service/helpcenter/category/img']);
    }
}