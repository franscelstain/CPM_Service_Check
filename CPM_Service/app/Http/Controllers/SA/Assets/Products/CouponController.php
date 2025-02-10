<?php

namespace App\Http\Controllers\SA\Assets\Products;

use App\Http\Controllers\AppController;
use App\Interfaces\Products\CouponRepositoryInterface;
use Illuminate\Http\Request;

class CouponController extends AppController
{
    private $couponRepo;
    
    public function __construct(CouponRepositoryInterface $couponRepo)
    {
        $this->couponRepo = $couponRepo;
    }

    public function deleteData($id)  
    {
        try
        {
            return $this->responseJson('Coupon', $this->couponRepo->deleteData($id));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function detailData($id)  
    {
        try
        {
            return $this->responseJson('Coupon', $this->couponRepo->detailData($id));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function getData(Request $request)  
    {
        try
        {
            return $this->responseJson('Coupon', $this->couponRepo->getData($request));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function import(Request $request)
    {   
        try
        {
            return $this->responseJson('Import Coupon', $this->couponRepo->importData($request));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function saveData(Request $request, $id)
    {
        try
        {
            $request->request->add(['coupon_rate' => str_replace(',', '', $request->coupon_rate)]);
            return $this->responseJson('Save Coupon', $this->couponRepo->saveData($request, $id));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}