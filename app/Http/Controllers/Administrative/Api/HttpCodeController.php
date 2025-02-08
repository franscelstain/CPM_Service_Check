<?php

namespace App\Http\Controllers\Administrative\Api;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class HttpCodeController extends AppController
{
    public $table = 'Administrative\Api\HttpCode';

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