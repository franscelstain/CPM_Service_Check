<?php

namespace App\Http\Controllers\Investor\Notify;


use App\Models\Administrative\Notify\SalesSetup;
use App\Models\Administrative\Notify\CategorySetup;
use App\Http\Controllers\AppController;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Database\QueryException;

class SalesNotificationController extends AppController
{
	public $table = 'Administrative\Notify\SalesSetup';

 	public function index(){
 		return $this->db_result();
 	}
	
    public function get_category(){

        $data      = CategorySetup::select('m_notification_category.*')
                    ->where('assign_to','sales')
                    ->get();

        return $this->app_response('Notification', ['list' => $data,'key'=>'id']);
    }

	public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function save(Request $request, $id = null)
    {
    	$request->request->add(['is_active' => 'Yes']);
    	$request->request->add(['created_by' => Auth::id()]);
    	$request->request->add(['updated_by' => Auth::id()]);
        return $this->db_save($request, $id);
    }

}
