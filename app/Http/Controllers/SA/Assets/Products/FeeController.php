<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Fee;
use Illuminate\Http\Request;

class FeeController extends AppController
{
    public $table = 'SA\Assets\Products\Fee';

    public function index()
    {
        try
        {
            $data   = Fee::select('m_products_fee.*', 'product_name', 'c.reference_value as fee_name')
                    ->join('m_products as b', 'b.product_id', '=', 'm_products_fee.product_id')
                    ->leftJoin('m_fee_reference as c', function ($qry) { return $qry->on('m_products_fee.fee_id', '=', 'c.fee_reference_id')->where([['c.is_active', 'Yes'], ['c.reference_type', 'Fee']]); })
                    ->where([['m_products_fee.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->get();
            return $this->app_response('ProductsFee', ['key' => 'fee_product_id', 'list' => $data]);  
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

    public function field(Request $request, $fn=[], $pid=0)
    {
        $filter = [
            'where' => [
                ['product_id', $pid],
                ['is_expired', 'No'],
                ['effective_date', '<=', $this->cpm_date()]
            ],
            'order' => ['effective_date' => 'DESC']
        ];
        return $this->db_row($fn, $filter);
    }
    
    public function save(Request $request, $id = null)
    {
        $request->request->add(['fee_value' => str_replace(',', '', $request->fee_value)]);
        return $this->db_save($request, $id);
    }
}