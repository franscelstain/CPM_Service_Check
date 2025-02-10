<?php

namespace App\Http\Controllers\Administrative\Service;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Service\Glossary;
use Illuminate\Http\Request;

class GlossariesController extends AppController
{
    public $table = 'Administrative\Service\Glossary';

    public function index()
    {
        return $this->db_result();
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function info(Request $request)
    {
        try
        {
            return $this->app_response('info', Glossary::select('glossary_name', 'glossary_text')->where([['glossary_code', $request->input('code')], ['is_active', 'Yes']])->first());            
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
}