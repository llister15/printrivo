<?php
namespace App\Filters;
use CodeIgniter\HTTP\RequestInterface;
class AdminAccess extends MemberAccess
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($response = parent::before($request, $arguments)) { return $response; }
        if (!auth()->user()->inGroup('admin')) {
            return service('response')->setStatusCode(403)->setBody('Administrator access required.');
        }
    }
}
