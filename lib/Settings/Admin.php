<?php
declare(strict_types=1);
namespace OCA\Soca\Settings;

use OCA\Soca\Service;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IURLGenerator;

final class Admin implements ISettings
{
    public function __construct(private Service $service, private IURLGenerator $urls) {}
    public function getForm(): TemplateResponse
    {
        try { $status = json_encode($this->service->flow()->status(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR); }
        catch (\Throwable) { $status = 'Configuration unavailable. Set SOCA_CONFIG_FILE and check deployment secret references.'; }
        return new TemplateResponse('soca', 'settings', ['status' => $status, 'check' => $this->urls->linkToRoute('soca.login.check'), 'test' => $this->urls->linkToRoute('soca.login.test'), 'requesttoken' => \OCP\Server::get(\OC\Security\CSRF\CsrfTokenManager::class)->getToken()->getEncryptedValue()]);
    }
    public function getSection(): string { return 'security'; }
    public function getPriority(): int { return 50; }
}
