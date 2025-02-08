<?php

namespace App\Http\Controllers\SA\UI;

use App\Http\Controllers\AppController;
use App\Models\SA\UI\Marquee;
use Illuminate\Http\Request;

class MarqueeController extends AppController
{
    public $table = 'SA\UI\Marquee';

    public function index()
    {
        return $this->db_result(['order' => ['sequence_to' => 'asc']]);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function running_text()
    {
        try
        {
            $marquee = Marquee::where('is_active', 'Yes')->orderBy('sequence_to')->get();
            return $this->app_response('Running Text', $marquee);
        }
        catch (\Exception $e)
        {
            return $this->appi_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
}