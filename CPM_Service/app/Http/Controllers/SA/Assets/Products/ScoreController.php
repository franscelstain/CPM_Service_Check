<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\Score;
use Illuminate\Http\Request;

class ScoreController extends AppController
{
    public $table = 'SA\Assets\Products\Score';  

    public function index(Request $request)
    {
        try
        {
            $page   = !empty($request->page) ? $request->page : 1;
            $search = !empty($request->search) ? $request->search : 1;
            $data = Score::select('t_products_scores.*', 'b.product_name', 'c.asset_class_name')
                    ->join('m_products as b', 't_products_scores.product_id', '=', 'b.product_id')
                    ->leftJoin('m_asset_class as c', function($qry) { return $qry->on('b.asset_class_id', '=', 'c.asset_class_id')->where('c.is_active', 'Yes'); })
                    ->where([['t_products_scores.is_active', 'Yes'], ['b.is_active', 'Yes']]);

            if (!empty($request->search)) 
                $data = $data->where('b.product_name', 'ilike', '%'. $request->search .'%')
                ->orWhere('c.asset_class_name', 'ilike', '%'. $request->search .'%');
            if (!empty($request->score_date))
                $data = $data->whereDate('score_date', '>=', $request->score_date);
            if (!empty($request->score_date2) ) 
                $data = $data->whereDate('score_date', '<=', $request->score_date2);

            return $this->app_response('Success get data', $data->paginate(10, ['*'], 'page', $page));

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
        $request->merge([
            'expected_return'    => str_replace(',', '', $request->input('expected_return')),
            'standard_deviation' => str_replace(',', '', $request->input('standard_deviation')),
            'sharpe_ratio'       => str_replace(',', '', $request->input('sharpe_ratio'))
        ]);
        return $this->db_save($request, $id);
    }
}