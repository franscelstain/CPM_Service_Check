<?php

namespace App\Http\Controllers\Administrative\Cronjob;

use App\Models\Transaction\TransactionHistory;
use App\Models\Financial\Condition\AssetLiability;
use App\Models\Financial\Condition\IncomeExpense;
use App\Models\Financial\AssetOutstanding;
use App\Models\Financial\LiabilityOutstanding;
use App\Models\Financial\Planning\Goal\Investment;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Question;
use App\Models\Users\Investor\Audit;
use App\Models\Users\Investor\Edd;
use App\Models\Users\Investor\EddFamily;
use App\Models\Users\Investor\ResetPassword;
use App\Models\Administrative\Notify\NotificationInvestor;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use DB;
use Auth;

class InvestorValidController extends AppController
{
    public function investor_valid()
    {
        try
        {
            $failed  = 0;
            $success = 0;
            $array   = [];
            $date   = date('Y-m-d H:i:s', strtotime('-3 day '. $this->app_date()));
            $data   = Investor::where([['valid_account', 'No'], ['created_at', '<',$date]])
                    ->get();
            foreach ($data as $dt ) {
                $array[] = $dt->investor_id;
                $transaction = TransactionHistory::where('investor_id', $dt->investor_id)->get();
                if(!empty($transaction))
                {
                    if(!$this->delete_investor($dt->investor_id))
                    {
                        $failed++;
                    }else{
                        $success++;
                    }
                }
                else
                {
                    $data   = Investor::where('investor_id', $dt->investor_id)->update(['is_active' => 'No']);
                    $success++;
                }
            }

            return $this->app_response('update investor valid account',['success' =>$success, 'failed' =>$failed, 'id investor' =>$array ]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function delete_investor($id)
    {
            // $table = ['h_notification_investor', 't_assets_liabilities', 't_assets_outstanding', 't_goal_investment', 't_income_expense', 't_liabilities_outstanding', 't_trans_histories', 'u_investors_accounts', 'u_investors_addresses', 'u_investors_edd', 'u_investors_password', 'u_investors_questions', 'u_investors'];
            // foreach ($table as $tbl ) {
            //     DB::table('u_investors_audit')->where('pk_id', $id)->delete();
            //     DB::table($tbl)->where('investor_id', $id)->delete();
            // }
            NotificationInvestor::where('investor_id', $id)->delete();
            Audit::where('pk_id', $id)->delete();            
            AssetLiability::where('investor_id', $id)->delete();
            IncomeExpense::where('investor_id', $id)->delete();
            AssetOutstanding::where('investor_id', $id)->delete();
            Investment::where('investor_id', $id)->delete();
            LiabilityOutstanding::where('investor_id', $id)->delete();
            TransactionHistory::where('investor_id', $id)->delete();
            Account::where('investor_id', $id)->delete();
            Address::where('investor_id', $id)->delete();
            Edd::where('investor_id', $id)->delete();
            EddFamily::where('investor_id', $id)->delete();
            ResetPassword::where('investor_id', $id)->delete();
            Question::where('investor_id', $id)->delete();
            Investor::where('investor_id', $id)->delete();
    }
}