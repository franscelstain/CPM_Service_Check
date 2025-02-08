<?php

namespace App\Http\Controllers\Administrative\Email;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class ContentEmailController extends AppController
{
    public $table = 'Administrative\Email\EmailContent';
    
    public function index(Request $request)
    {
        $whr = !empty ($request->layout_id) ? ['where' => [['c_email_contents.layout_id', $request->layout_id]]] : [];
        return $this->db_result(array_merge(['join' => [['tbl' => 'c_email_layouts', 'key' => 'layout_id', 'select' => ['layout_title']]]], $whr));
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