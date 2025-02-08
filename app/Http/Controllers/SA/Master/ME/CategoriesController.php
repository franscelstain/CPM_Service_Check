<?php

namespace App\Http\Controllers\SA\Master\ME;

use App\Http\Controllers\AppController;
use App\Models\SA\Master\ME\Category;
use Illuminate\Http\Request;

class CategoriesController extends AppController
{
    public $table = 'SA\Master\ME\Category';

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
}