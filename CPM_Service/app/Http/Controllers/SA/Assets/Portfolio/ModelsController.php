<?php

namespace App\Http\Controllers\SA\Assets\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Portfolio\Models;
use Illuminate\Http\Request;

class ModelsController extends AppController
{
    public $table = 'SA\Assets\Portfolio\Models';

    public function index()
    {
        try
        {
            $data   = Models::select('m_models.*', 'b.product_name as benchmark_name', 'c.product_name as benchmark_name2')
                    ->leftJoin('m_products as b', function ($join) { $join->on('m_models.benchmark', '=', 'b.product_id')->where('b.is_active', 'Yes'); })
                    ->leftJoin('m_products as c', function ($join) { $join->on('m_models.benchmark2', '=', 'c.product_id')->where('c.is_active', 'Yes'); })
                    ->where('m_models.is_active', 'Yes')->get();
            return $this->app_response('Model', ['key' => 'model_id', 'list' => $data]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
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