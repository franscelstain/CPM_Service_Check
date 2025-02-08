<?php

namespace App\Http\Controllers\SA\Reference;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class GoalsController extends AppController
{
    public $table = 'SA\Reference\Goal';
    
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