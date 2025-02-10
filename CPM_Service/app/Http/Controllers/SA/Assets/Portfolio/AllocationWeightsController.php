<?php

namespace App\Http\Controllers\SA\Assets\Portfolio;

use App\Http\Controllers\AppController;
use App\Models\SA\Assets\Portfolio\AllocationWeightDetail;
use Illuminate\Http\Request;

class AllocationWeightsController extends AppController
{
    public $table = 'SA\Assets\Portfolio\AllocationWeight';

    public function index()
    {
        return $this->db_result(['join' => [['tbl' => 'm_models', 'key' => 'model_id', 'select' => ['model_name']]]]);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function detail_weight(Request $request)
    {
        try
        {
            $wgh = [];
            if (!empty($request->input('id')))
            {
                $data = AllocationWeightDetail::where([['allocation_weight_id', $request->input('id')], ['is_active', 'Yes']])->get();
                foreach ($data as $dt)
                {
                    $wgh[] = ['product_id' => $dt->product_id, 'weight' => $dt->weight];
                }
            }
            if (empty($wgh))
            {
                $wgh[] = ['product_id' => '', 'weight' => ''];
            }
            return $this->app_response('Weight Detail', $wgh);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        $id = $this->db_save($request, $id, ['res' => 'id']);
        
        AllocationWeightDetail::where('allocation_weight_id', $id)->update(['is_active' => 'No']);
        
        $success    = 1;
        $product    = $request->input('product_id');
        $weight     = $request->input('weight');
        for ($i = 0; $i < count($product); $i++)
        {
            $qry  = AllocationWeightDetail::where([['allocation_weight_id', $id], ['product_id', $product[$i]]])->first();   
            $data = [
                'allocation_weight_id'  => $id, 
                'product_id'            => $product[$i], 
                'weight'                => $weight[$i],
                'is_active'             => 'Yes',
                'created_by'            => $this->auth_user()->id,
                'created_host'          => $request->input('ip')
            ];
            $save = empty($qry->allocation_weight_detail_id) ? AllocationWeightDetail::create($data) : AllocationWeightDetail::where('allocation_weight_detail_id', $qry->allocation_weight_detail_id)->update($data);
            $success++;
        }
        return $this->app_partials($success, 0, ['id' => $id]);
    }
}