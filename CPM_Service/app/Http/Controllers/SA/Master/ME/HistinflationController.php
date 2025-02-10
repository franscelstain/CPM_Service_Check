<?php

namespace App\Http\Controllers\SA\Master\ME;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class HistinflationController extends AppController
{
    public $table = 'SA\Master\ME\HistInflation';

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