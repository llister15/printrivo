<?php
namespace App\Controllers;
use CodeIgniter\HTTP\ResponseInterface;
class BugReport extends BaseController
{
    public function send(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control','private, no-store');
        foreach (['tool','summary','description','expected'] as $field) {
            $value=$this->request->getPost($field);
            if ($value!==null && !is_string($value)) {
                return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'error'=>'Please enter text in the report fields.']);
            }
        }
        if (!$this->validateData($this->request->getPost(),[
            'tool'=>'required|max_length[80]',
            'summary'=>'required|max_length[120]',
            'description'=>'required|min_length[10]|max_length[3000]',
            'expected'=>'permit_empty|max_length[1000]',
        ])) {
            return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'error'=>'Please add a short summary and at least 10 characters describing the problem. Keep the summary under 120 characters, description under 3,000, and expected result under 1,000.']);
        }
        $app=null;
        foreach (config('Suite')->apps as $candidate) {
            if ($candidate['route']===$this->request->getPost('tool')) { $app=$candidate; break; }
        }
        if (!$app) { return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'error'=>'Please open the report form from one of your tools.']); }
        if (!config('BugReports')->ready()) {
            return $this->response->setStatusCode(503)->setJSON(['ok'=>false,'error'=>'Bug reports are temporarily unavailable. Your report has not been sent. Please try again later.']);
        }
        // Per-account limit prevents the endpoint from being used to flood the support inbox.
        if (!service('throttler')->check('bug-report-'.auth()->id(),5,HOUR)) {
            return $this->response->setStatusCode(429)->setJSON(['ok'=>false,'error'=>'You have sent several reports recently. Please wait an hour before trying again.']);
        }
        $sent=service('bugReportMailer')->send(auth()->user(),$app,trim($this->request->getPost('summary')),
            trim($this->request->getPost('description')),trim((string)$this->request->getPost('expected')));
        if (!$sent) { return $this->response->setStatusCode(503)->setJSON(['ok'=>false,'error'=>'We could not send your report. Your text is still here; please try again later.']); }
        return $this->response->setJSON(['ok'=>true,'message'=>'Your report has been sent. Thanks for helping improve Printrivo!']);
    }
}
