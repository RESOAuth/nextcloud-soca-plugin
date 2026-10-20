<?php
declare(strict_types=1);
namespace OCA\Soca\Controller;

use OCA\Soca\Service;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IUserSession;
use RuntimeException;

final class LoginController extends Controller
{
    public function __construct(IRequest $request, private Service $service, private IUserSession $userSession) { parent::__construct('soca', $request); }
    #[PublicPage] #[NoCSRFRequired]
    public function metadata(): JSONResponse { return new JSONResponse($this->service->client()->metadata(), 200, ['Cache-Control' => 'public, max-age=300']); }
    #[PublicPage] #[NoCSRFRequired]
    public function jwks(): JSONResponse { return new JSONResponse($this->service->client()->jwks(), 200, ['Cache-Control' => 'public, max-age=300']); }
    #[PublicPage] #[NoCSRFRequired] #[UseSession] #[BruteForceProtection(action: 'socaLogin')]
    public function start(): RedirectResponse|JSONResponse
    {
        try {
            if ($this->request->getServerProtocol() !== 'https') { throw new RuntimeException('HTTPS is required.'); }
            if ($this->userSession->isLoggedIn()) { throw new RuntimeException('Use the administrator test action within an existing session.'); }
            return new RedirectResponse($this->service->flow()->begin());
        } catch (\Throwable $e) { return $this->failure($e); }
    }
    #[PublicPage] #[NoCSRFRequired] #[UseSession] #[BruteForceProtection(action: 'socaCallback')]
    public function callback(): RedirectResponse|JSONResponse
    {
        try {
            if ($this->request->getServerProtocol() !== 'https') { throw new RuntimeException('HTTPS is required.'); }
            $query = [];
            foreach (['code','state','iss','error','error_description'] as $key) { $value = $this->request->getParam($key); if ($value !== null) { $query[$key] = $value; } }
            $result = $this->service->flow()->complete($query, $this->service->operator());
            if ($result['test']) { return new JSONResponse(['test_login_succeeded' => true, 'message' => 'The candidate passed. The operator session was preserved.']); }
            if ($this->userSession->isLoggedIn()) { throw new RuntimeException('A local session must not be replaced.'); }
            $user = $this->service->account($result['claims']);
            if (!$this->userSession instanceof \OC\User\Session) { throw new RuntimeException('Unsupported native session implementation.'); }
            if (!$this->userSession->completeLogin($user, ['loginName' => $user->getUID(), 'password' => null])) { throw new RuntimeException('Native login failed.'); }
            $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());
            $this->service->store->set('soca.auth', ['uid' => $user->getUID(), 'revision' => $this->service->flow()->revision(), 'at' => time()]);
            return new RedirectResponse($this->service->config()['origin'] . '/index.php/apps/files/');
        } catch (\Throwable $e) { return $this->failure($e); }
    }
    #[UseSession]
    public function check(): JSONResponse
    {
        try { if ($this->service->operator() === '') { throw new RuntimeException('Local administrator required.'); } return new JSONResponse($this->service->flow()->checkDocuments()); }
        catch (\Throwable $e) { return $this->failure($e); }
    }
    #[UseSession]
    public function test(): RedirectResponse|JSONResponse
    {
        try { if ($this->request->getServerProtocol() !== 'https') { throw new RuntimeException('HTTPS is required.'); } return new RedirectResponse($this->service->flow()->begin(true, $this->service->operator())); }
        catch (\Throwable $e) { return $this->failure($e); }
    }
    private function failure(\Throwable $error): JSONResponse
    {
        $reference = bin2hex(random_bytes(8));
        $this->service->store->audit('login_rejected', $reference);
        // Never log callback values, tokens, claims or provider exception messages.
        return new JSONResponse(['error' => 'SOCA could not complete this action. Check deployment configuration, candidate evidence, admission and account links.', 'reference' => $reference], 403, ['Cache-Control' => 'no-store']);
    }
}
