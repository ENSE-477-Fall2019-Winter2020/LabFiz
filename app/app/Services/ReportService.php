<?php


namespace App\Services;


use App\Contracts\RestServiceContract;
use App\Report;
use App\ReportQuestion;
use App\ReportSection;
use App\Repositories\ModelRepository;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;

class ReportService implements RestServiceContract
{
    protected $report_model, $report_section_model, $report_question_model, $user_model;

    public function __construct(Report $report, ReportQuestion $report_question, ReportSection $report_section, User $user)
    {
        $this->report_model = new ModelRepository($report);
        $this->report_section_model = new ModelRepository($report_section);
        $this->report_question_model = new ModelRepository($report_question);
        $this->user_model = new ModelRepository($user);
    }

    public function get($id)
    {
        $result = ['status' => '400 (Bad Request)', 'message' => '', 'data' => ''];

        try {
            $issue = $this->model_report->getById($id);

        } catch (QueryException $ex) {
            $result['message'] = $ex->getMessage();
            return ['response' => $result, 'status' => 400];
        }

        $result['data'] = $issue;
        $result['status'] = '200 (Ok)';
        $result['message'] = 'Report retrieved successfully.';
        return ['response' => $result, 'status' => 200];
    }

    public function get_all()
    {
        $result = ['status' => '400 (Bad Request)', 'message' => '', 'data' => ''];
        $result['data'] = $this->model_report->get();
        $result['status'] = '200 (Ok)';
        $result['message'] = 'All Reports retrieved successfully.';
        return ['response' => $result, 'status' => 200];
    }

    public function create(FormRequest $request)
    {
        $result = ['status' => '400 (Bad Request)', 'message' => '', 'data' => []];

        $header = $request->header('Authorization');
        $user = $this->model_user->getByColumn($header, 'api_token');
        $sections = $request->sections;

        // create the report
        try {
            $report = $this->model_report->updateOrCreate(
                ['id' => $request->id],
                [
                    'title' => $request->title,
                    'report_template_id' => $request->template_id,
                    'user_id' => $user->id,
                    'room' => $request->room,
                    'due_date' => $request->due_date
                ]
            );
        } catch (QueryException $ex) {
            $result['message'] = $ex->getMessage();
            return ['response' => $result, 'status' => 400];
        }

        $result['data'] = [
            'id' => $report->id,
            'title' => $report->title,
            'user_id'=> $report->user_id,
            'created_at'=> $report->created_at,
            'updated_at' => $report->updated_at,
            'report_template_id' => $report->report_template_id,
            'room' => $report->room,
            'due_date' => $report->due_date
        ];

        // if the report has sections, populate the tables for sections and questions
        if ($sections) {
            foreach ($sections as $sect_key => $sect_val) {

                // create the section
                try {
                    $section = $this->model_report_section->updateOrCreate(
                        ['report_id' => $request->id, 'title' => $sect_key],
                        [
                            'title' => $sect_key,
                            'report_id' => $report->id,
                            'user_id' => $user->id,
                            'report_section_template_id' => $sect_val['template_id']
                        ]
                    );
                } catch (QueryException $ex) {
                    $result['message'] = $ex->getMessage();
                    return ['response' => $result, 'status' => 400];
                }

                $result['data']['ref'][$sect_key] = [
                    'id' => $section->id,
                    'title' => $section->title,
                    'report_id' => $section->report_id,
                    'created_at'=> $section->created_at,
                    'updated_at' => $section->updated_at,
                    'report_section_template_id' => $section->report_template_section_id
                ];

                // create any questions
                foreach ($sect_val['qs'] as $question_key => $question_val) {
                    try {
                        $question = $this->model_report_question->updateOrCreate(
                            [
                                'report_section_id' => $section->id,
                                'question' => $question_key
                            ],
                            [
                                'question' => $question_key,
                                'report_section_id' => $section->id,
                                'report_question_template_id' => $question_val['template_id'],
                                'answer' => $question_val['answer'],
                                'description' => $question_val['description']
                            ]
                        );
                    } catch (QueryException $ex) {
                        $result['message'] = $ex->getMessage();
                        return ['response' => $result, 'status' => 400];
                    }

                    $result['data']['ref'][$sect_key]['ref'][$question_key] = $question;
                }
            }
        }

        $result['status'] = '200 (Ok)';
        $result['message'] = 'Created report document successfully!';
        return ['response' => $result, 'status' => 200];
    }

    public function delete($id)
    {
        $result = ['status' => '400 (Bad Request)', 'message' => '', 'data' => ''];

        try {
            $result['data'] = $this->model_report->deleteById($id);
        } catch (QueryException $ex) {
            $result['message'] = $ex->getMessage();
            return ['response' => $result, 'status' => 400];
        }

        $result['status'] = '200 (Ok)';
        $result['message'] = 'Report deleted successfully.';
        return ['response' => $result, 'status' => 200];
    }
}
