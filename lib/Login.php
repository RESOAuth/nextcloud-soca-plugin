<?php
declare(strict_types=1);
namespace OCA\Soca;

final class Login implements \OCP\Authentication\IAlternativeLogin
{
    public function __construct(private \OCP\IURLGenerator $urls) {}
    public function getLabel(): string { return 'Sign in with SOCA'; }
    public function getLink(): string { return $this->urls->linkToRoute('soca.login.start'); }
    public function getClass(): string { return ''; }
    public function load(): void {}
}
