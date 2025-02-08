<?php

namespace App\Http\Controllers\SA\Master\ME;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class PricesController extends AppController
{
    public $table = 'SA\Master\ME\Price';

    public function index()
    {
        $filter = ['join' => [['tbl' => 'm_macro_economic_categories', 'key' => 'me_category_id', 'select' => ['me_category_name']]]];
        return $this->db_result($filter);
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