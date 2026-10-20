<?php
declare(strict_types=1);
namespace OCA\Soca\AppInfo;

use OCA\Soca\Login;
use OCA\Soca\Service;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap
{
    public function __construct(array $urlParams = []) { parent::__construct('soca', $urlParams); }
    public function register(IRegistrationContext $context): void
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $context->registerAlternativeLogin(Login::class);
    }
    public function boot(IBootContext $context): void
    {
        $context->injectFn(static function (Service $service): void { $service->enforceSession(); });
    }
}
