<?php

namespace App\Http\Controllers\SA\Reference\KYC\RiskProfiles;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\KYC\RiskProfiles\Answer;
use Illuminate\Http\Request;

class AnswerController extends AppController
{
    public $table = 'SA\Reference\KYC\RiskProfiles\Answer';

    public function index()
    {
        $filter = ['join' => [['tbl' => 'm_profile_questions', 'key' => 'question_id', 'select' => ['question_text']]]];
        return $this->db_result($filter);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }

    public function question(Request $request)
    {
        try
        {
            $answer  = [];
            if (!empty($request->input('id')))
            {
                $data   = Answer::where([['question_id', $request->input('id')], ['is_active', 'Yes']])->get();
                foreach ($data as $dt)
                {
                    $answer[] = ['answer_text' => $dt->answer_text, 'answer_score' => $dt->answer_score, 'icon' => $dt->icon];
                }
            }
            if (empty($answer))
            {
                $answer[] = ['answer_text' => '', 'answer_score' => '', 'icon' => ''];
            }
            return $this->app_response('Answer Detail', $answer);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        return $this->db_save($request, $id, ['path' => 'riskprofiles/answer/img']);
    }
}