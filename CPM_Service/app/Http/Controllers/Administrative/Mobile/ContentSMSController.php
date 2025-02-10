<?php

namespace App\Http\Controllers\Administrative\Mobile;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class ContentSMSController extends AppController
{
    public $table = 'Administrative\Mobile\MobileContent';
    
    public function index(Request $request)
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