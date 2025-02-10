<?php

namespace App\Http\Controllers\Administrative\Api;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Api\Host;
use Illuminate\Http\Request;


class HostController extends AppController
{
    public $table = 'Administrative\Api\Host';

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