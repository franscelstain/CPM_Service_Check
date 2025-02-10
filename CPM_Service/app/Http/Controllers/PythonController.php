<?php

namespace App\Http\Controllers;

use App\Models\SA\Assets\Products\Price;
use App\Models\SA\Assets\Products\Product;
use App\Models\SA\Assets\Products\Allocation;
use Illuminate\Http\Request;
use Auth;

class PythonController extends CpmController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }
    
    public function score()
    {
        $prd_id     = [];
        $prd_date   = [];
        $prd_val    = [];
        $m_score    = new \App\Models\SA\Assets\Products\Score;
        $m_scorehst = new \App\Models\SA\Assets\Products\ScoreHist;
        $product    = Product::all();
        if ($product->count() > 0)
        {
            foreach ($product as $prd)
            {
                $prd_id[] = $prd->ProductID;
            }
            
            $price = Price::whereIn('ProductID', $prd_id)->orderBy('PriceDate')->orderBy('ProductID')->get();
            if ($price->count() > 0)
            {
                $nd = '';
                foreach ($price as $row)
                {
                    if ($nd != $row->PriceDate)
                    {
                        if (!empty($nd))
                        {
                            $prd_date[] = $nd;
                            $prd_val[]  = $dt;
                            $dt         = [];
                        }
                        $nd = $row->PriceDate;
                    }
                    $dt[]   = in_array($row->ProductID, $prd_id) ? $row->PriceValue : 0;
                    $date   = $row->PriceDate;
                }
                $prd_date[] = $date;
                $prd_val[]  = $dt;
            }
        }
        $data = __api(['url' => 'score', 'data' => ['ProductID' => $prd_id, 'PriceDate' => $prd_date, 'PriceValue' => $prd_val]]);
        foreach ($data as $dt)
        {
            $score  = $m_score::where([['ProductID', $dt['ProductID']], ['is_active', 'Yes']])->first();
            $authby = !empty($score->ProductScoreID) ? 'UpdatedBy' : 'CreatedBy';
            $ds     = ['ProductID'         => $dt['ProductID'], 
                       'ScoreDate'         => $this->cpm_date(),
                       'ExpectedReturn'    => $dt['ExpectedReturn'],
                       'StandardDeviation' => $dt['StandardDeviation'],
                       'SharpeRatio'       => $dt['SharpeRatio'],
                       $authby             => 'System'
                      ];
            $qs     = !empty($score->ProductScoreID) ? $m_score::where('ProductScoreID', $score->ProductScoreID)->update($ds) : 
                      $m_score::create($ds);
            if (!empty($score->ProductScoreID))
            {
                $dsh = [
                    'ProductScoreID'    => $score->ProductScoreID,
                    'ProductID'         => $score->ProductID,
                    'ScoreDate'         => $score->ScoreDate,
                    'ExpectedReturn'    => $score->ExpectedReturn,
                    'StandardDeviation' => $score->StandardDeviation,
                    'SharpeRatio'       => $score->SharpeRatio,
                    'CreatedBy'         => 'System'
                ];
                $m_scorehst::create($dsh);
            }
        }
    }
    
    public function models(Request $request)
    {
        try
        {
            $prd_id     = [];
            $prd_arr    = [];
            $prd_date   = [];
            $prd_val    = [];
            $product    = Allocation::where('ModelID', $request->input('model_id'))->whereNotNull('ProductID')->get();
            $fee_prd    = new SA\Assets\Products\FeeController;
            if ($product->count() > 0)
            {
                foreach ($product as $prd)
                {
                    if (!empty($prd->ProductID))
                    {
                        $prd_id[]   = $prd->ProductID;
                        $fee        = $fee_prd->field($request, ['FeeProductID', 'FeePercentage'], $prd->ProductID)->original['data'];
                        $prd_arr[$prd->ProductID] = [
                            'AssetClassID'      => $prd->AssetClassID,
                            'AssetClassName'    => $prd->AssetClassName,
                            'ExpectedReturn'    => $prd->ExpectedReturn,
                            'FeePercentage'     => !empty($fee['FeePercentage']) ? $fee['FeePercentage'] : 0,
                            'FeeProductID'      => $fee['FeeProductID'],
                            'InvestType'        => $prd->InvestType,
                            'ProductID'         => $prd->ProductID,
                            'ProductName'       => $prd->ProductName,
                            'ReferenceLink1'    => $prd->ReferenceLink1,
                            'SharpeRatio'       => $prd->SharpeRatio
                        ];
                    }
                }

                $price = Price::whereIn('ProductID', $prd_id)->orderBy('PriceDate')->orderBy('ProductID')->get();
                if ($price->count() > 0)
                {
                    $nd = '';
                    foreach ($price as $row)
                    {
                        if ($nd != $row->PriceDate)
                        {
                            if (!empty($nd))
                            {
                                $prd_date[] = $nd;
                                $prd_val[]  = $dt;
                                $dt         = [];
                            }
                            $nd = $row->PriceDate;
                        }
                        $dt[]   = in_array($row->ProductID, $prd_id) ? $row->PriceValue : 0;
                        $date   = $row->PriceDate;
                    }
                    $prd_date[] = $date;
                    $prd_val[]  = $dt;
                }
            }
            $prd_id = array_values(array_unique($prd_id));
            $data   = __api(['url' => 'model', 'data' => ['ProductID' => $prd_id, 'PriceDate' => $prd_date, 'PriceValue' => $prd_val]]);
            if (!empty($data))
            {
                foreach ($data['Model'] as $dt_key => $dt_val)
                {
                    $prd_arr[$dt_key] = array_merge($prd_arr[$dt_key], ['Allocation' => $dt_val]);
                }
            }
            return $this->api_response('Python Model', $prd_arr);
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
    }
}
