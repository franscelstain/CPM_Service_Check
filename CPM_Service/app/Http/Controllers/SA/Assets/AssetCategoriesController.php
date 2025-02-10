<?php

namespace App\Http\Controllers\SA\Assets;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class AssetCategoriesController extends AppController
{
    public $table = 'SA\Assets\AssetCategory';

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
        $request->request->add(['diversification_account' => $request->input('diversification_account') == 'true' ? 't' : 'f']);
        return $this->db_save($request, $id);
    }
}