<?php

namespace App\Http\Controllers\SA\UI;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class IconsController extends AppController
{
    public $table = 'SA\UI\Icon';
    
    public function index(Request $request)
    {
        return $this->db_result(['where' => [['icon_type', $request->input('icon')]]]);
    }
}