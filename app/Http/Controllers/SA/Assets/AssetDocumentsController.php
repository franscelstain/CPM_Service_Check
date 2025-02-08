<?php

namespace App\Http\Controllers\SA\Assets;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class AssetDocumentsController extends AppController
{
    public $table = 'SA\Assets\AssetDocument';

    public function index()
    {
        return $this->db_result(['join' => [['tbl' => 'm_asset_categories', 'key' => 'asset_category_id', 'select' => ['asset_category_name']]]]);
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