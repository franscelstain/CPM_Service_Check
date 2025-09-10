<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AppController;
use App\Interfaces\Users\InvestorRepositoryInterface;
use App\Services\Handlers\Users\Investor\InvestorService;
use Illuminate\Http\Request;
use Auth;

class InvestorsController extends AppController
{
    private $invRepo;
    protected $invService;
    
    public function __construct(InvestorService $invService, InvestorRepositoryInterface $invRepo)
    {
        $this->invRepo = $invRepo;
        $this->invService = $invService;
    }

    public function detailInvestor($id)
    {
        return $this->responseJson('Investor - Detail', $this->invRepo->detailInvestor($id));
    }

    public function detailInvestorBySales($inv_id)
    {
        try {
            $auth = Auth::guard('admin')->user();
            if ($auth->usercategory_name == 'Sales') {
                $inv = $this->invRepo->detailInvestorBySales($inv_id, $auth->id);
                if ($inv->investor_id) {
                    return $this->app_response('Investor Detail', $inv);
                } else {
                    return $this->app_response('Not Found', [], ['error_msg' => ['Investor data not found.'], 'error_code' => 404]);
                }
            } else {
                return $this->app_response('Permission Denied', [], ['error_msg' => ['User is not authorized to access this investor detail.'], 'error_code' => 403]);
            }            
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function eStatement()
    {
        return $this->responseJson('Investor - eStatement', $this->invRepo->eStatement());
    }

    public function listInvestor(Request $request)
    {
        return $this->responseJson('Investor', $this->invRepo->listInvestor($request));
    }

    public function listPriorityCard(Request $request)
    {
        try {
            $search = $request->input('search');
            $limit = $request->input('limit', 10); // Default limit 10
            $colName = $request->input('colName', 'fullname');
            $colSort = $request->input('colSort', 'asc');
            $page = $request->input('page');
            $inv = $this->invRepo->listPriorityCard($search, $limit, $page, $colName, $colSort);
            $total = !empty($search) ? $this->invRepo->countInvestorPriority() : $inv->total();
    
            return $this->app_response('Investor - Priority', [
                'item' => $inv->items(),
                'current_page' => $inv->currentPage(),
                'last_page' => $inv->lastPage(),
                'per_page' => $inv->perPage(),
                'total' => $total,
                'total_filtered' => $inv->total(), // Adding filtered total
            ]);    
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function listWithBalanceForSales(Request $request)
    {
        try {
            $auth = Auth::guard('admin')->user();
            if ($auth->usercategory_name == 'Sales') {
                $search = $request->input('search');
                $limit = $request->input('limit', 10); // Default limit 10
                $colName = $request->input('colName');
                $colSort = $request->input('colSort', 'asc');
                $page = $request->input('page');
                $inv = $this->invRepo->listWithBalanceForSales($auth->id, $search, $limit, $page, $colName, $colSort);
                $total = !empty($search) ? $this->invRepo->countInvestorsBySales($auth->id) : $inv->total();
        
                return $this->app_response('Investor - Priority', [
                    'item' => $inv->items(),
                    'current_page' => $inv->currentPage(),
                    'last_page' => $inv->lastPage(),
                    'per_page' => $inv->perPage(),
                    'total' => $total,
                    'total_filtered' => $inv->total(), // Adding filtered total
                ]);
            } else {
                return $this->app_response('Permission Denied', [], ['error_msg' => ['User is not authorized to access this investor list.'], 'error_code' => 403]);
            }
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function listWithGoalsForSales(Request $request)
    {
        try {
            $auth = Auth::guard('admin')->user();
            if ($auth->usercategory_name == 'Sales') {
                $search = $request->input('search');
                $limit = $request->input('limit', 10); // Default limit 10
                $colName = $request->input('colName', 'cif');
                $colSort = $request->input('colSort', 'asc');
                $page = $request->input('page');

                $response = $this->invService->listWithGoalsWithSales($auth->id, $search, $limit, $page, $colName, $colSort);
                
                return $this->app_response('Investor', $response);
            } else {
                return $this->app_response('Permission Denied', [], ['error_msg' => ['User is not authorized to access this investor list.'], 'error_code' => 403]);
            }
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function totalInvestor()
    {
        return $this->responseJson('Investor', $this->invRepo->totalInvestor());
    }
}