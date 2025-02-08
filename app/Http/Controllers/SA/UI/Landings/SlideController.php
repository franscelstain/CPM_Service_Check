<?php

namespace App\Http\Controllers\SA\UI\Landings;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class SlideController extends AppController
{
    public $table ='SA\UI\Landings\Slide';
    
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
        return $this->db_save($request, $id, ['path' => 'slide/img']);
    }
}