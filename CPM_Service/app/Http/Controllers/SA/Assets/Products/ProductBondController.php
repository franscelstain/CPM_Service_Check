<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
//use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Bond;
use Illuminate\Http\Request;

class ProductBondController extends AppController
{
    public $table = 'SA\Assets\Products\Bond';

    public function index()
    {
        try
        {
            $data   = Bond::select('m_products_bonds.*', 'product_name')
                    ->join('m_products as b', 'b.product_id', '=', 'm_products_bonds.product_id')
                    //->leftJoin('m_fee_reference as c', function ($qry) { return $qry->on('m_products_fee.fee_id', '=', 'c.fee_reference_id')->where([['c.is_active', 'Yes'], ['c.reference_type', 'Fee']]); })
                    //->where([['m_products_fee.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    ->get();
            return $this->app_response('ProductsBond', ['key' => 'bond_id', 'list' => $data]);  
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail($id)
    {
        $id = 1;
        return $this->db_detail($id);

        
    }

    protected function db_detail($id=null, $ele=[])
    {
        try
        {
            $model  = $this->db_model();
            $spec   = isset($ele['specific']) ? $ele['specific'] : [];
            $filter = isset($ele['filter']) ? $ele['filter'] : [];
            $whr    = !empty($spec) ? $spec : [$model->getKeyName(), $id]; 
            $data   = $id > 0 || (!empty($spec) && is_array($spec)) ? $model::where(array_merge([$whr, ['is_active', 'Yes']], $filter))->first() : array_fill_keys($this->db_column($model), '');

            if (empty($id) && array_key_exists('sequence_to', $data)) $data['sequence_to'] = $this->db_sequence($filter);

            return $this->app_response('Succes get detail', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

   //  public function field(Request $request, $fn=[], $pid=0)
   //  {
   //      $filter = [
   //          'where' => [
   //              ['product_id', $pid],
   //              ['is_expired', 'No'],
   //              ['effective_date', '<=', $this->cpm_date()]
   //          ],
   //          'order' => ['effective_date' => 'DESC']
   //      ];
   //      return $this->db_row($fn, $filter);
   //  }
    
    public function save(Request $request, $id = null)
    {
        $request->request->add(['fee_value' => str_replace(',', '', $request->fee_value)]);
        return $this->db_save($request, $id);
    }
}