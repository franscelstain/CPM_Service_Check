<?php

namespace App\Http\Controllers\SA\Assets\Portfolio;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class AllocationController extends AppController
{
    public $table = 'SA\Assets\Portfolio\Allocation';

    public function index()
    {
        $filter = ['join' => [
            ['tbl' => 'm_asset_class', 'key' => 'asset_class_id', 'select' => ['asset_class_name']], 
            ['tbl' => 'm_models', 'key' => 'model_id', 'select' => ['model_name']]
        ]];
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