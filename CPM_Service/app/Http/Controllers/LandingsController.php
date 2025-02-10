<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AppController;
use App\Models\Qna;
use App\Models\Administrative\Config\Config;
use App\Models\Administrative\Config\Logo;
use App\Models\SA\UI\Landings\Feature;
use App\Models\SA\UI\Landings\Menu;
use App\Models\SA\UI\Landings\Module;
use App\Models\SA\UI\Landings\Partner;
use App\Models\SA\UI\Landings\Slide;
use App\Models\SA\UI\Marquee;
use App\Models\SA\UI\SocialMedia;
use Illuminate\Http\Request;

class LandingsController extends AppController
{
    public $table = 'Qna';

    public function index()
    {
        try 
        {
            return $this->app_response('Landings', [
                'feature'   => Feature::select('feature_name', 'feature_img')->where('is_active', 'Yes')->orderBy('sequence_to')->get(),
                'logo'      => Logo::where('is_active', 'Yes')->first(),
                'marquee'   => Marquee::select('marquee_name', 'marquee_slug', 'marquee_text')->where('is_active', 'Yes')->orderBy('sequence_to')->get(), 
                'menu'      => Menu::select('menu_name')->where('is_active', 'Yes')->orderBy('sequence_to')->get(), 
                'module'    => Module::select('module_title', 'module_subtitle', 'module_text', 'module_img')->where('is_active', 'Yes')->orderBy('sequence_to')->get(), 
                'partner'   => Partner::select('partner_img')->where([['is_active', 'Yes'], ['partner_view', 'Yes']])->orderBy('sequence_to')->get(), 
                'slide'     => Slide::select('slide_img')->where([['is_active', 'Yes'], ['slide_view', 'Yes']])->orderBy('sequence_to')->get(), 
                'socmed'    => SocialMedia::select('socmed_slug', 'socmed_icon_landing')->where([['is_active', 'Yes'], ['socmed_view', 'Yes']])->orderBy('sequence_to')->get(), 
                'speed'     => Config::select('config_value as speed')->where([['is_active', 'Yes'], ['config_name', 'SpeedMarquee']])->first()
            ]);
        } catch(\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function questions(Request $request, $id = null)
    {
        return $this->db_save($request, $id);
    }

    public function get_message()
    {
        try
        {
            $message  = Qna::select('t_questions.*')
                    // ->join('u_investors as b', 'b.email', '=', 't_questions.email')
                    ->where([['t_questions.is_active', 'Yes']])
                    ->orderBy('created_at', 'DESC')
                    ->get();
            
            return $this->app_response('Message', $message);
        }
        catch(\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}
