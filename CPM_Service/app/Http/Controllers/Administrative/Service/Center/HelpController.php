<?php

namespace App\Http\Controllers\Administrative\Service\Center;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Service\Center\Help;
use Illuminate\Http\Request;

class HelpController extends AppController
{
    public $table = 'Administrative\Service\Center\Help';

    public function index()
    {
        $filter = ['join' => [['tbl' => 'c_help_center_categories', 'key' => 'category_id', 'select' => ['category_name']]]];
        return $this->db_result($filter);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }
    
    public function published(Request $request, $slug='')
    {
        try
        {
            $arr    = [];
            $qry    = Help::select('help_id', 'help_name', 'help_text', 'slug', 'b.category_id', 'category_name', 'category_image')
                    ->join('c_help_center_categories as b', 'c_help_center.category_id', '=', 'b.category_id')
                    ->where([['c_help_center.is_active', 'Yes'], ['b.is_active', 'Yes']]);
            if (empty($slug))
            {
                if (!empty($request->search))
                {
                    $arr = $qry->where('help_name', 'like', '%'. $request->search .'%')->get();
                }
                else
                {
                    $data = $qry->orderBy('b.category_id')->get();
                    if ($data->count() > 0)
                    {   
                        $help = $cat = [];
                        foreach ($data as $dt)
                        {
                            $cat[$dt->category_id] = ['name' => $dt->category_name, 'img' => $dt->category_image];
                            $help[] = ['category_id' => $dt->category_id, 'name' => $dt->help_name, 'slug' => $dt->slug];
                        }
                        $arr = ['help' => $help, 'category' => $cat];
                    }
                }
            }
            else
            {
                $data = $qry->where('slug', $slug)->first();
                if (!empty($data->help_id))
                {
                    $rlt = Help::where([['is_active', 'Yes'], ['help_id', '!=', $data->help_id], ['category_id', $data->category_id]])->inRandomOrder()->limit(5)->get();
                    $arr = ['data' => $data, 'related' => $rlt];
                }
            }
            return $this->app_response('Help Center', $arr);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}