<?php
namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\SubscriptionAccess;
class MemberAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();
        $json = $request->isAJAX() || $request->getMethod() !== 'GET' || str_starts_with(uri_string(), 'api/');
        if (!$user || !auth()->loggedIn() || $user->isBanned() || !$user->active) {
            if ($user) { auth()->logout(); }
            return $json
                ? service('response')->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Please log in to continue.'])
                : redirect()->to(site_url('login'));
        }
        if (in_array('subscription', $arguments ?? [], true) && !(new SubscriptionAccess())->activeFor((int) $user->id)) {
            return $json
                ? service('response')->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'An active subscription is required.'])
                : redirect()->to(site_url('account/subscription'));
        }
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'private, no-store');
    }
}
