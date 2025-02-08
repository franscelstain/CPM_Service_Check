<?php

namespace App\Http\Controllers\SA\Master\FinancialCheckUp;

use App\Http\Controllers\AppController;
use App\Models\SA\Master\FinancialCheckUp\Financial;
use App\Models\SA\Master\FinancialCheckUp\FinancialAsset;
use App\Models\SA\Reference\Group;
use Illuminate\Http\Request;

class FinancialController extends AppController
{
    public $table = 'SA\Master\FinancialCheckUp\Financial';

    public function index(Request $request)
    {
        $type   = isset($request->type) ? ['where' => [['financial_type', $request->type]]] : ['where_in' => ['financial_type' => $this->finTyp($request->path())]];
        $filter = array_merge(['order' => ['financial_type' => 'asc', 'sequence_to' => 'asc']], $type);
        return $this->db_result($filter);
    }

    public function assetclass($id)
    {
        try
        {
            $res  = [];
            $data = FinancialAsset::where([['financial_id', $id], ['is_active', 'Yes']])->get();
            foreach ($data as $dt ) {
                $res[] = $dt->asset_class_id;
            }
            return $this->app_response('Financial', $res);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detail(Request $request, $id)
    {
        $id     = is_numeric($id) ? $id : 0;
        $data   = Financial::where([['financial_id', $id], ['is_active', 'Yes']])->whereIn('financial_type', $this->finTyp($request->path()))->first();
        $res    = !empty($data->financial_id) ? $data : array_fill_keys($this->db_column(new Financial), '');
        return $this->app_response('Financial', $res);
    }

    private function finTyp($path='')
    {
        $uri    = explode('/', $path);
        $type   = $uri[2] == 'list' ? $uri[1] : $uri[2];
        return array_map(function($e) { return ucwords($e); }, explode('-', $type));
    }

    public function fund_source(Request $request)
    {
        $filter = ['where' => [['financial_type', 'Income']], 'order' => ['sequence_to' => 'asc']];
        return $this->db_result($filter);
    }

    public function save(Request $request, $id = null)
    {
        try
        {
            if (in_array($request->financial_type, ['Assets', 'Liabilities']))
                $request->request->add(['is_liquidity' => $request->is_liquidity == 'Yes' ? 't' : 'f' ]);
            $save = $this->db_save($request, $id,['res' => 'id']);

            if (in_array($request->financial_type, ['Assets', 'Liabilities']))
            {
                FinancialAsset::where([['financial_id', $save]])->update(['is_active' => 'No']);                
                $asset = $request->asset_class_id;                
                if (!empty($asset))
                {
                    $auth = $this->db_manager($request);
                    foreach ($asset as $ass)
                    {
                        $fa     = FinancialAsset::where([['financial_id', $save], ['asset_class_id', $ass]])->first();
                        $sv     = empty($fa->financial_asset_id) ? 'cre': 'upd';
                        $datafa = ['financial_id' => $save, 'asset_class_id' => $ass, $sv. 'ated_by' => $auth->user, $sv. 'ated_host' => $auth->ip, 'is_active' => 'Yes'];
                        $savefa = empty($fa->financial_asset_id) ? FinancialAsset::create($datafa) : FinancialAsset::where('financial_asset_id', $fa->financial_asset_id)->update($datafa);     
                    }
                }
            }
            return $this->app_partials(1, 0, ['id' => $save]);            
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function seqTo($type)
    {
        try
        {
            $type   = [['financial_type', $type]];
            $seq_to = $this->db_sequence($type);
            return $this->app_response('Sequence To', $seq_to);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}