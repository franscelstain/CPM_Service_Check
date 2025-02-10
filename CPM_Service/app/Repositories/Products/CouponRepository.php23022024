<?php

namespace App\Repositories\Products;

use App\Imports\SA\Assets\Products\CouponImport;
use App\Interfaces\Products\CouponRepositoryInterface;
use App\Models\SA\Assets\Products\Coupon;
use App\Models\SA\Assets\Products\Product;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\Responses;
use DB;

class CouponRepository implements CouponRepositoryInterface
{
    use Responses;
    public function detailData($id)
    {
        try
        {
            return Coupon::join('m_products as b', 'b.product_id', 't_coupon.product_id')
                    ->where([['coupon_id', $id], ['deleted', false], ['b.is_active', 'Yes']])
                    ->select('t_coupon.*', 'b.product_name')
                    ->first();
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function deleteData($id)
    {
        try
        {
            return Coupon::where('coupon_id', $id)->update(['deleted' => true]);
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function getData($filter)
    {
        try
        {
            $order      = $filter->order;
            $colIdx     = !empty($order[0]['column']) ? $order[0]['column'] : '';
            $colSort    = $filter->sort ?? 'asc';
            $colName    = !empty($filter->columns[$colIdx]) ? $filter->columns[$colIdx]['data'] : 'product_name';
            $offset     = !empty($filter->start) ? $filter->start : 0;
            $limit      = !empty($filter->length) ? $filter->length : 1;
            $search     = !empty($filter->search) ? $filter->search['value'] : '';
            $qryAll     = Coupon::join('m_products as b', 'b.product_id', '=', 't_coupon.product_id')
                        ->where([['t_coupon.deleted', false], ['b.is_active', 'Yes']])
                        ->select('coupon_id', 'coupon_type', 'coupon_rate', 'product_name',
                                 DB::raw("TO_CHAR(coupon_date, 'YYYY-MM-DD') as coupon_date"));
            $total      = $qryAll->count();
            
            if (!empty($search))
            {
                $like = env('DB_CONNECTION') == 'pgsql' ? 'ilike' : 'like';
                $qryAll->where(function($qry) use ($search, $like) {
                    $qry->where('b.product_name', $like, '%'. $search .'%')
                        ->orWhere(DB::raw("TO_CHAR(t_coupon.coupon_date, 'YYYY-MM-DD')"), $like, '%'. $search .'%')
                        ->orWhere('t_coupon.coupon_rate', $like, '%'. str_replace(',', '', $search) .'%')
                        ->orWhere('t_coupon.coupon_type', $like, '%'. $search .'%');
                });
            }

            $totalFiltered = $qryAll->count();

            if (!empty($colName))
                $qryAll->orderBy($colName, $colSort);
            
            return [
                'draw' => $filter->draw ?? 1,
                'data' => $qryAll->limit($limit)->offset($offset)->get(), 
                'recordsFiltered' =>  $totalFiltered,
                'recordsTotal' => $total
            ];
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function importData($request)
    {
        try
        {

            $validator = $this->validation($request->all(), Coupon::rulesImport());
            if (!empty($validator))
            {
                return $validator;
            }
            else
            {            
                $fail       = $success = 0;
                $dtlSucc    = $dtlFail =  [];
                $usrtyp     = !empty($this->authUser()) ? $this->authUser()->usercategory_name : 'Visitor';
                $usrnm      = !empty($this->authUser()) ? $this->authUser()->fullname : 'User';
                $usrid      = !empty($this->authUser()) ? $this->authUser()->id : 0;
                $ip         = !empty($request->input('ip')) ? $request->input('ip') : $request->ip();
                $file       = $request->file('file_import');
                $new_file   = md5(uniqid()) . '.' . $file->getClientOriginalExtension();
                
                $file->move(storage_path('import'), $new_file);
                
                $excel  = Excel::toArray(new CouponImport, storage_path('import') .'/'. $new_file);
                $no     = 0;            
                foreach ($excel[0] as $ex)
                {
                    if (!empty($ex[0]))
                    {
                        if ($no > 0)
                        {
                            $err = [];                    
                            if (!is_numeric($ex[3]))
                                $err[] = $ex[0] . ' format date invalid';
                            if (!is_numeric($ex[2]))
                                $err[] = $ex[0] . ' rate ('. $ex[2] .') must be numeric'; 
                            
                            if (empty($err))
                            {
                                $prd = Product::where([['product_code', $ex[0]], ['is_active', 'Yes']])->first();
                                if (!empty($prd->product_id))
                                {
                                    $date   = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($ex[3]);
                                    $data   = [
                                                'product_id'    => $prd->product_id, 
                                                'coupon_date'   => \Carbon\Carbon::instance($date), 
                                                'coupon_type'   => $ex[1],
                                                'coupon_rate'   => $ex[2],
                                                'created_by'    => $usrtyp.':'.$usrid.':'.$usrnm,
                                                'created_host'  => $ip
                                            ];
                                    if ($qry = Coupon::create($data))
                                    {
                                        $data['id'] = $qry->coupon_id;
                                        array_push($dtlSucc, $data);
                                        $success++;
                                    }
                                }
                                else
                                {
                                    array_push($dtlFail, [$ex[0] . ' data not found']);
                                    $fail++;
                                }
                            }
                            else
                            {
                                array_push($dtlFail, $err);
                                $fail++;
                            }
                        }
                        $no++;
                    }
                }

                unlink(storage_path('import') .'/'. $new_file);

                return ['success' => ['total' => $success, 'data' => $dtlSucc], 'fail' => ['total' => $fail, 'error' => $dtlFail]];
            }
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }

    public function saveData($data, $id)
    {
        try
        {
            $validator = $this->validation($data->all(), Coupon::rules());
            if (!empty($validator))
            {
                return $validator;
            }
            else
            {
                $detail = $this->detailData($id);
                if (!empty($detail->coupon_id))
                {
                    $save = [
                        'coupon_rate'   => $data->coupon_rate,
                        'coupon_date'   => $data->coupon_date,
                        'coupon_type'   => $data->coupon_type,
                    ];
                    return Coupon::where('coupon_id', $id)->update($save);
                }
                return (object) ['errors' => ['error_code' => 500, 'error_msg' => ['Data not found']]];
            }
        }
        catch (\Exception $e)
        {
            return (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
    }
}