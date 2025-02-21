<?php

namespace App\Http\Controllers\SA\Reference\KYC\RiskProfiles;

use App\Http\Controllers\AppController;
use App\Models\SA\Reference\KYC\InvestorType;
use App\Models\SA\Reference\KYC\RiskProfiles\Answer;
use App\Models\SA\Reference\KYC\RiskProfiles\Question;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class QuestionController extends AppController
{
    public $table = 'SA\Reference\KYC\RiskProfiles\Question';

    public function index()
    {
        $filter = ['join' => [['tbl' => 'm_investor_types', 'key' => 'investor_type_id', 'select' => ['investor_type_name']]]];
        return $this->db_result($filter);
    }

    public function detail($id)
    {
        return $this->db_detail($id);
    }
    
    public function questionnaire()
    {
        try
        {
            $data       = [];
            $question   = Question::join('m_investor_types as b', 'm_profile_questions.investor_type_id', '=', 'b.investor_type_id')
                        ->where([['b.investor_type_name', 'Individual'], ['m_profile_questions.is_active', 'Yes'], ['b.is_active', 'Yes'], ['published', 'Yes']])
                        ->orderBy('sequence_to')
                        ->get();
            foreach ($question as $qst)
            {
                $answer = Answer::select('answer_id', 'answer_text', 'icon')->where([['question_id', $qst->question_id], ['is_active', 'Yes']])->orderBy('sequence_to')->get();
                $data[] = [
                            'question_id'       => $qst->question_id,
                            'question_text'     => $qst->question_text,
                            'question_title'    => $qst->question_title,
                            'answer_icon'       => $qst->answer_icon,
                            'answer'            => $answer
                        ];
            }
            return $this->app_response('Qustioner', $data);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request, $id = null)
    {
        $success    = 1;
        if($request->method() != 'DELETE')
        {
            $published  = !empty($request->input('published')) ? $request->input('published') : 'No';
            $request->request->add(['published' => $published]);
            
            $id         = $this->db_save($request, $id, ['res' => 'id']);
            $ans_text   = $request->input('answer_text');
            $ans_score  = $request->input('answer_score');
            $ans_icon   = $request->file('icon');
            $client     = new Client(); 
            
            Answer::where('question_id', $id)->update(['is_active' => 'No']);
            for ($i = 0; $i < count($ans_text); $i++)
            {
                $answer = Answer::where([['question_id', $id], ['answer_text', $ans_text[$i]]])->first();
                $st     = empty($answer->answer_id) ? 'cre' : 'upd';
                $data   = ['question_id'   => $id,
                           'answer_text'   => $ans_text[$i],
                           'answer_score'  => $ans_score[$i],
                           'is_active'     => 'Yes',
                           $st.'ated_by'   => $this->auth_user()->id,
                           $st.'ated_host' => $request->input('ip')
                          ];
                $save   = empty($answer->answer_id) ? Answer::create($data) : Answer::where('answer_id', $answer->answer_id)->update($data);
                if (!empty($ans_icon[$i]) && $request->input('answer_icon') == 'Yes')
                {
                    $ans_id     = empty($answer->answer_id) ? $save->answer_id : $answer->answer_id;
                    $filename   = $ans_id .'_'. md5('Icon' . $this->app_date('','Y-m-d H:i:s')) .'.'. $ans_icon[$i]->getClientOriginalExtension();
                    $client->post(env('API_UPLOAD') . 'upload', [
                        'multipart' => [
                            ['name' => 'file', 'contents' => file_get_contents($ans_icon[$i]), 'filename' => $filename],
                            ['name' => 'path', 'contents' => 'riskprofiles/answer/img/' . $ans_id]
                        ]
                    ]);
                    Answer::where('answer_id', $ans_id)->update(['icon' => $filename]);
                }
                $success++;
            }
            return $this->app_partials($success, 0, ['id' => $id]);
        }
        else
        {
            return $this->db_save($request, $id);
        }
    }
    
    public function ws_data(Request $request)
    {
        try
        {
            $insert     = $update = $insAns = $updAns = 0;
            $data       = [];
            $invtype    = InvestorType::where('is_active', 'Yes')->get();
            foreach ($invtype as $inv)
            {
                $api = $this->api_ws(['sn' => 'RiskProfileQuestionnaire', 'val' => [$inv->ext_code]])->original['data'];
                foreach ($api as $a)
                {
                    $qry    = Question::where([['ext_code', $a->id]])->first();
                    $id     = !empty($qry->question_id) ? $qry->question_id : null;
                    $request->request->add([
                        'investor_type_id'  => $inv->investor_type_id,
                        'question_text'     => $a->questionText,
                        'question_title'    => $a->questionTitle,
                        'sequence_to'       => $a->questionNo,
                        'description'       => $a->remarks,
                        'ext_code'          => $a->id,
                        'is_data'           => !empty($id) ? $qry->is_data : 'WS',
                        '__update'          => !empty($id) ? 'Yes' : ''
                    ]);
                    $qst = $this->db_save($request, $id, ['validate' => true, 'res' => 'id']);
                    
                    foreach ($a->options as $opt)
                    {
                        $qAns   = Answer::where([['ext_code', $opt->id]])->first();
                        $idAns  = !empty($qAns->answer_id) ? $qAns->answer_id : null;
                        $request->request->add([
                            'question_id'   => $qst,
                            'answer_text'   => $opt->optionText,
                            'answer_score'  => $opt->optionValue,
                            'sequence_to'   => $opt->optionNo,
                            'description'   => $opt->remarks,
                            'ext_code'      => $opt->id,
                            'is_data'       => !empty($idAns) ? $qry->is_data : 'WS',
                            '__update'      => !empty($idAns) ? 'Yes' : ''
                        ]);
                        $this->db_save($request, $idAns, ['validate' => true, 'table' => 'SA\Reference\KYC\RiskProfiles\Answer']);
                        
                        if (empty($idAns))
                            $insAns++;
                        else
                            $updAns++;
                    }

                    if (empty($id))
                        $insert++;
                    else
                        $update++;
                }
            }
            return $this->app_partials($insert+$update, 0, ['save' => ['question' => ['insert' => $insert, 'update' => $update], 'answer' => ['insert' => $insAns, 'update' => $updAns]]]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}