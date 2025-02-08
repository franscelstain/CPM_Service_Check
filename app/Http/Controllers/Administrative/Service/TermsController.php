<?php

namespace App\Http\Controllers\Administrative\Service;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Service\Terms;
use Illuminate\Http\Request;

class TermsController extends AppController
{
    public $table = 'Administrative\Service\Terms';
    
    
    public function index()
    {
        return $this->db_result();
    }
    
    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }

    public function terms_code($code)
    {
        try
        {
            $data = Terms::where([['terms_code', $code], ['is_active', 'Yes']])->first();
            return $this->app_response('terms code', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}