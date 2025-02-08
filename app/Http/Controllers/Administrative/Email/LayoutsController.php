<?php

namespace App\Http\Controllers\Administrative\Email;

use App\Http\Controllers\AppController;
use Illuminate\Http\Request;

class LayoutsController extends AppController
{
    public $table = 'Administrative\Email\EmailLayout';
    
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

    public function sendmail(Request $request)
    {   
        try
        {
            return $this->app_response('send email', $this->app_sendmail(['to' => $request->input('to'), 'content' => $request->input('content')]));
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}