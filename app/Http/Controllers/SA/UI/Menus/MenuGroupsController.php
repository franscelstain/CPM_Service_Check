<?php

namespace App\Http\Controllers\SA\UI\Menus;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class MenuGroupsController extends AppController
{
    public $table = 'SA\UI\Menus\MenuGroup';
    
    public function index()
    {
        return $this->db_result(['order' => ['sequence_to' => 'asc']]);
    }
    
    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function save(Request $request, $id=null)
    {
        return $this->db_save($request, $id);
    }
}