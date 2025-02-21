<?php

namespace App\Http\Controllers\Sales;

use App\Models\Investor\Financial\Planning\Current\Outstanding;
use App\Http\Controllers\AppController;
use App\Models\Users\Investor\Investor;
use App\Models\Transaction\TransactionHistoryDay;
use Illuminate\Http\Request;


class InvestorController extends AppController
{

    public function index($id = '')
    {
        $data = Investor::select()
            ->join('m_risk_profiles as c', 'c.profile_id', '=', 'u_investors.profile_id')
            ->where([['u_investors.sales_id', $this->auth_user()->id], ['u_investors.is_active', 'Yes']])
            ->get();
        $inv = [];
        foreach ($data as $dt) {
            $inv[] = [
                'investor_id' => $dt->investor_id,
                'cif' => $dt->cif,
                'fullName' => $dt->fullname,
                'photo_profile' => $dt->photo_profile,
                'sid' => $dt->sid,
                'profile_name' => $dt->profile_name,
                'profile_expired_date' => $dt->profile_expired_date,
                'balance_amount' => $this->outstanding($dt->investor_id)
            ];
        }
        return $this->app_response('investor', $inv);
    }

    public function outstanding($investor_id)
    {
        $qry = Outstanding::select('balance_amount')
            ->where([['t_assets_outstanding.investor_id', $investor_id], ['t_assets_outstanding.is_active', 'Yes'], ['t_assets_outstanding.outstanding_date', $this->app_date()]])
            ->sum('balance_amount');

        return $qry;
    }

    public function detail_current(Request $request, $investor_id)
    {
        try {
            $data = [];
            $inv = Investor::leftJoin('m_risk_profiles as b', function ($qry) {
                $qry->on('u_investors.profile_id', '=', 'b.profile_id')->where('b.is_active', 'Yes');
            })
                ->where([['u_investors.investor_id', $investor_id], ['u_investors.sales_id', $this->auth_user()->id], ['u_investors.is_active', 'Yes']])->first();
            $inv_nm = !empty($inv->investor_id) ? $inv->fullname : '';
            $prf_nm = !empty($inv->investor_id) ? $inv->profile_name : '';

            if (!empty($inv->investor_id)) {
                $data = Outstanding::select('t_assets_outstanding.*', 'c.product_name', 'c.product_code', 'd.asset_class_id', 'd.asset_class_name', 'd.asset_class_color', 'e.issuer_logo')
                    ->join('u_investors as b', 't_assets_outstanding.investor_id', '=', 'b.investor_id')
                    ->join('m_products as c', 't_assets_outstanding.product_id', '=', 'c.product_id')
                    ->leftJoin('m_asset_class as d', function ($qry) {
                        $qry->on('c.asset_class_id', '=', 'd.asset_class_id')->where('d.is_active', 'Yes');
                    })
                    ->leftJoin('m_issuer as e', function ($qry) {
                        $qry->on('c.issuer_id', '=', 'e.issuer_id')->where('e.is_active', 'Yes');
                    })
                    ->where([['t_assets_outstanding.investor_id', $investor_id], ['t_assets_outstanding.outstanding_date', $this->app_date()], ['t_assets_outstanding.is_active', 'Yes'], ['b.is_active', 'Yes'], ['c.is_active', 'Yes']]);

                if (!empty($request->search))
                    $data = $data->where('c.product_name', 'ilike', '%' . $request->search . '%');
                if (!empty($request->asset_class_id))
                    $data = $data->where('d.asset_class_id', $request->asset_class_id);
                if (!empty($request->balance_minimum))
                    $data = $data->where('balance_amount', '>=', $request->balance_minimum);
                if (!empty($request->balance_maximum))
                    $data = $data->where('balance_amount', '<=', $request->balance_maximum);

                $data = $data->distinct()->get();
                $total = !empty($request->total) ? ['total' => $this->list_total($data)] : [];

                return $this->app_response('Current Portfolio', array_merge(['data' => $data, 'profilename' => $prf_nm, 'investor' => $inv_nm], $total));
            }

            // return $this->app_response('Current Portfolio', ['detail' => $data, 'investor' => $inv_nm, 'profilename' =>$prf_nm]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    private function list_total($item)
    {
        $asset = $account = $product = [];
        $balance = $earning = $return = 0;
        foreach ($item as $i) {
            if (in_array($i->asset_class_id, array_keys($asset))) {
                $asset[$i->asset_class_id]['amount'] += $i->balance_amount;
            } else {
                $asset[$i->asset_class_id] = ['name' => $i->asset_class_name, 'amount' => floatval($i->balance_amount), 'color' => $i->asset_class_color];
            }
            $product[] = ['product_name' => $i->product_name, 'balance_amount' => $i->balance_amount, 'account_no' => $i->account_no];
            $account[] = $i->account_no;
            $balance += $i->balance_amount;
            $earning += $i->return_amount;
        }
        return ['account_no' => count($account), 'asset' => array_values($asset), 'balance' => $balance, 'earning' => $earning, 'product' => $product, 'returns' => $return];
    }

    public function max_aum()
    {
        try {
            $balance = [];
            $investor = TransactionHistoryDay::selectRaw('SUM(t_trans_histories_days.current_balance) as current_balances, b.investor_id, b.fullname, b.sid, b.photo_profile')
                ->join('u_investors as b', 't_trans_histories_days.investor_id', '=', 'b.investor_id')
                ->where([['t_trans_histories_days.is_active', 'Yes'], ['t_trans_histories_days.history_date', $this->app_date()], ['b.is_active', 'Yes'], ['b.sales_id', $this->auth_user()->id]])
                ->groupBy('b.investor_id')
                ->orderBy('current_balances', 'desc')
                ->limit(5)
                ->get();

            foreach ($investor as $inve) {
                $balance[] = [
                    'investor_id' => $inve->investor_id,
                    'fullname' => $inve->fullname,
                    'sid' => $inve->sid,
                    'photo_profile' => $inve->photo_profile,
                    'goals' => floatval(TransactionHistoryDay::where([['is_active', 'Yes'], ['history_date', $this->app_date()], ['investor_id', $inve->investor_id]])->whereRaw("left(portfolio_id, 1)= '2'")->sum('current_balance')),
                    'non_goals' => floatval(TransactionHistoryDay::where([['is_active', 'Yes'], ['history_date', $this->app_date()], ['investor_id', $inve->investor_id]])->whereNull('portfolio_id')->sum('current_balance')),
                    'retirement' => floatval(TransactionHistoryDay::where([['is_active', 'Yes'], ['history_date', $this->app_date()], ['investor_id', $inve->investor_id]])->whereRaw("left(portfolio_id, 1)= '3'")->sum('current_balance'))
                ];

            }

            return $this->app_response('investor', $balance);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function investor_by_roles(Request $request)
    {
        try {
            $get_data = isset($request->get_data) ? $request->get_data : '';
            $internal = isset($request->internal) ? $request->internal : '';
            $limit = !empty($request->limit) ? $request->limit : 10;
            $page = !empty($request->page) ? $request->page : 1;
            $data = \App\Models\Auth\Investor::select(['investors.*'])
                ->where([['investors.is_active', 'Yes']]);
            $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
            if ($get_data != 'first' && $internal != 'yes') {
                if (!empty($request->search)) {

                    $data->where(function ($qry) use ($request, $like) {
                        $qry->where('investors.fullname', $like, '%' . $request->search . '%')
                            ->orWhere('investors.cif', $like, '%' . $request->search . '%')
                            ->orWhere('investors.sid', $like, '%' . $request->search . '%')
                            ->orWhere('investors.profile_name', $like, '%' . $request->search . '%');
                    });
                }
            }

            if (!empty($request->start_date))
                $data->where('investors.profile_expired_date', '>=', $request->start_date);
            if (!empty($request->end_date))
                $data->where('investors.profile_expired_date', '<=', $request->end_date);

            if (!empty($request->riskprofiles))
                $data->where('investors.profile_id', $request->riskprofiles);

            if (isset($request->investor_id))
                $data->where('investors.investor_id', $request->investor_id);

            if (isset($request->cif))
                $data->where('investors.cif', $request->cif);

            if ($get_data == 'first')
                $data = $data->first();
            elseif ($limit == '~' || (isset($request->no_paging) && $request->no_paging == 'yes')) {
                if (!empty($request->limit) && $limit != '~')
                    $data->limit($limit);
                $data = $data->get();
            } else
                $data = $data->paginate($limit, ['*'], 'page', $page);

            return $internal == 'yes' ? $data : $this->app_response('Investor', $data);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
}
