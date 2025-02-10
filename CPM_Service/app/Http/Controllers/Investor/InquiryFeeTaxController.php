<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use App\Models\SA\Assets\Products\Product;
use App\Models\Users\Investor\Account;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use stdClass;

class InquiryFeeTaxController extends AppController
{
       public function getInvestorData(): Investor
       {
              return Investor::where('investor_id', Auth::id())->first();
       }
       public function getCifNumber()
       {
              return $this->getInvestorData()->cif;
       }
       public function externalEndpointUri(): string
       {
              return (string) "http://192.168.26.14/BSM/AdapterInt/TransactionESB.svc/InquiryFeeTax";
       }
       public function getProductCode($request)
       {
              $productIdentifier = (int) $request;
              $product = DB::table('m_products')->where('product_id', '=', $productIdentifier)->first();
              return $product;
       }
       public function generateRequestBody(Request $request)
       {
              # fromat untuk pengiriman request fEE tax Inquery ke wms
              # cif number di dapat dari nilai cif pada user terlogin (generate otomatis dr sistem di service)
              # customer bank acccount data dari input select bank account yang di lakukan oleh user di CPM di forward ke sini .
              # begitu juga dengan data yang lain. 
              $data = [
                     "cIF"                       => $request->cif, // $this->getCifNumber()
                     "channelID"                 => "CPM",
                     "custBankAccountNo"         => $request->custBankAccountNo,
                     "productCode"               => $this->getProductCode($request->productId)->product_code,
                     "transactionCategory"       => $request->transactionCategory,
                     "_TransactionValue"         => [
                            (object) [
                                   "InvAccountNo" => $request->TransactionValue[0]['InvAccountNo'],
                                   "IsRedeemAll"  => (bool) false,
                                   "NetAmount"    => (int) $request->TransactionValue[0]['NetAmount']
                            ]
                     ]
              ];
              return $data;
       }

       public function sendRequest(Request $request)
       {
              $data['payload'] = $this->generateRequestBody($request);
              // return response()->json($data['payload']);
              // try {
              $response = Http::acceptJson()->post(
                     $this->externalEndpointUri(),
                     $data['payload']
              );
              return $response->json();
              // } catch (Exception $e) {
              //        return $this->app_catch($e);
              // }
       }

    public function feetaxadapter(Request $request)
    {
        try
        {
            $fee = $tax =  $fee_unit = 0;
            if ($this->auth_user()->usercategory_name == 'Investor')
            {
                $cif        = $this->auth_user()->cif;
            }
            else
            {
                $investor   = Investor::where([['investor_id', $request->investor_id], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
                $cif        = !empty($investor->cif) ? $investor->cif : '';  
            }
            
            if(!empty($cif))
            {
                $product        = Product::where([['is_active', 'Yes'], ['product_id', $request->product_id]])->first();
                //$product_code   = !empty($product->product_code) ? $product->product_code : '';
                $product_code   = !empty($product->ext_code) ? $product->ext_code : '';
                $account        = Account::where([['is_active', 'Yes'], ['investor_account_id', $request->investor_account_id]])->first();
                $account_no     = !empty($account->account_no) ? $account->account_no : '';

                if($request->transcategory  == 'SUB') {
                  $data = [[
                      'InvAccountNo'  => (strtoupper($request->transcategory) == 'SUB') ? '' : $account_no,
                      'IsRedeemAll'   => true,
                      'NetAmount'     => floatval($request->netamount)]
                  ];
                }elseif ($request->transcategory == 'SWT'){
                   $product_to =  Product::where([['is_active', 'Yes'], ['product_id', $request->product_code_to]])->first();
                   $product_code_to   = !empty($product_to->ext_code) ? $product_to->ext_code : '';
                   // return  $request->sub_account;
                    $data = [[
                      //'InvAccountNo'  => 'NEW8307148312',
                      'InvAccountNo'  => $request->sub_account,  
                      'IsRedeemAll'   => false,
                      'NetAmount'     => 0,
                      'Units'         => floatval($request->unitredeem)]
                    ];
                }else {
                   $data = [[
                      'InvAccountNo'  => $request->sub_account, 
                      'IsRedeemAll'   => false,
                      //'NetAmount'     => 0,
                      'NetAmount'     => floatval($request->netamount),
                      'Units'         => floatval($request->unitredeem)]
                  ];
                }

                /*
                if($request->transcategory  == 'RED') {
                  $data = [[
                      'InvAccountNo'  => '79796300 15 REGULER', 
                      'IsRedeemAll'   => false,
                      'NetAmount'     => 20,
                      'Units'         => 30]
                  ];
                }
                            /*
                  $data = [[
                      'InvAccountNo'  => $request->sub_account, 
                      'IsRedeemAll'   => false,
                      'NetAmount'     => floatval($request->netamount),
                      'Units'         => floatval($request->unitredeem)]
                  ];
                */  

                /*
                  $data = [[
                      'InvAccountNo'  => $request->sub_account, 
                      'IsRedeemAll'   => false,
                      'NetAmount'     => floatval($request->netamount)
                  ];  
                  */              
                /* sample test api untuk testing jalan ke API bersama tester */
                /*
                $cif = "83071483";
                $account_no = "7777284988"; 
                $product_code = "MREF";
                $product_code_to = "MITRAS";
                $request->transcategory = "SWT";
                $data = [[
                    'InvAccountNo'  => 'NEW8307148312',
                    'IsRedeemAll'   => false,
                    'NetAmount'     => 0,
                    'NetAmount'     => 700000]
                ];
                */

                //$api = $this->api_ws(['sn' => 'FeeTaxAdapter', 'val' => [$cif, $account_no, $product_code, $request->transcategory, $data]])->original['message']->Result->_TaxFeeListOut;
                //FeeTaxAdapterSWT
                if ($request->transcategory == 'SWT')
                {
                  $api = $this->api_ws(['sn' => 'FeeTaxAdapterSWT', 'val' => [$cif, $account_no, $product_code, $product_code_to, $request->transcategory, $data]]);
                }else{
                  $api = $this->api_ws(['sn' => 'FeeTaxAdapter', 'val' => [$cif, $account_no, $product_code, $request->transcategory, $data]]);    
                } 

                if (!empty($api->original['message']))
                {
                    if(!empty($api->original['message']->Result) && !empty($api->original['message']->Result->_TaxFeeListOut))
                    {
                        $feeTax = $api->original['message']->Result->_TaxFeeListOut[0];
                        $fee = $feeTax->FeeAmount;
                        $tax = $feeTax->TaxAmount; 
                        $fee_unit = $request->transcategory == 'RED' ? $feeTax->FeeAmount : 0;  
                        $message = $api->original['message'];
                        $message = $api->original['message']->Message;                                              
                        $isSuccess = $api->original['message']->IsSuccess;
                  }
                }
            }
            return $this->app_response('fee', ['fee_amount' => $fee, 'tax_amount' => $tax, 'fee_unit' => $fee_unit, 'message' => $api->original['message'], 'isSuccess' => $api->original['message']->IsSuccess]);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}
