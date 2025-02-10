<?php

namespace App\Http\Controllers\Administrative\Api;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Api\Errorcode;
use Illuminate\Http\Request;

class ErrorCodeController extends AppController
{
    public $table = 'Administrative\Api\Errorcode';

    public function index()
    {
        $filter = ['join' => [['tbl' => 'c_api_http_code', 'key' => 'http_id', 'select' => ['http_code']]]];
        return $this->db_result($filter);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function httpcode()
    {
        return $this->app_response('HTTP Code', ErrorCode::whereNull('parent_code')->get());
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
}