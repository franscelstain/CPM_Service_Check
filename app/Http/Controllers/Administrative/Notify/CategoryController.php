<?php

namespace App\Http\Controllers\Administrative\Notify;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class CategoryController extends AppController
{
	public $table = 'Administrative\Notify\CategorySetup';

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