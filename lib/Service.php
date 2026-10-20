<?php
declare(strict_types=1);
namespace OCA\Soca;

use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUserSession;
use RESOAuth\Soca\Client;
use RESOAuth\SocaHost\Configuration;
use RESOAuth\SocaHost\Flow;
use RESOAuth\SocaHost\Identity;
use RuntimeException;

final class Service
{
    public function __construct(public Store $store, private IConfig $nativeConfig, private IUserManager $users, private IGroupManager $groups, private IUserSession $userSession) {}
    public function config(): array
    {
        return Configuration::load(['client_name' => $this->nativeConfig->getSystemValue('instance_name', 'Nextcloud')], [
            'redirect_uri' => '/index.php/apps/soca/callback', 'metadata_uri' => '/index.php/apps/soca/metadata', 'jwks_uri' => '/index.php/apps/soca/jwks',
        ]);
    }
    public function client(): Client { return new Client(Configuration::protocol($this->config())); }
    public function flow(): Flow { $c = $this->config(); return new Flow(new Client(Configuration::protocol($c)), $this->store, $c); }
    public function operator(): string
    {
        $u = $this->userSession->getUser();
        return $u !== null && $this->groups->isAdmin($u->getUID()) ? $u->getUID() : '';
    }
    public function eligible(string $uid): IUser
    {
        $u = $this->users->get($uid);
        $group = (string)$this->config()['group_id'];
        if ($u === null || !$u->isEnabled() || $this->groups->isAdmin($uid) || $group === '' || !$this->groups->isInGroup($uid, $group)) {
            throw new RuntimeException('The linked local account is unavailable or is not eligible for SSO.');
        }
        return $u;
    }
    public function account(array $claims): IUser
    {
        $config = $this->config();
        $hash = Identity::hash($config['issuer'], $claims['sub']);
        $uid = $this->store->linkedUid($hash);
        if ($uid !== null) { return $this->eligible($uid); }
        if (!$config['provision']) { throw new RuntimeException('No approved local account link exists.'); }
        $group = $this->groups->get((string)$config['group_id']);
        if ($group === null || $group->getGID() === 'admin') { throw new RuntimeException('Configure an existing least-privileged group.'); }
        $profile = Identity::profile($claims);
        $uid = Identity::generatedUid($hash);
        $this->store->db->beginTransaction();
        try {
            if ($this->users->get($uid) !== null) { throw new RuntimeException('Account identifier collision requires administrator recovery.'); }
            $user = $this->users->createUser($uid, base64_encode(random_bytes(64)));
            if ($user === false) { throw new RuntimeException('Native account creation failed.'); }
            $group->addUser($user);
            if (isset($profile['name'])) { $user->setDisplayName($profile['name']); }
            if (isset($profile['email'])) { $user->setEMailAddress($profile['email']); }
            $this->store->link($hash, $uid);
            $this->store->db->commit();
            $this->store->audit('account_provisioned', $hash);
            return $this->eligible($uid);
        } catch (\Throwable $e) {
            $this->store->db->rollBack();
            throw $e;
        }
    }
    public function enforceSession(): void
    {
        $marker = $this->store->get('soca.auth');
        if (!is_array($marker)) { return; }
        try {
            $config = $this->config();
            $valid = $config['enabled'] && !Configuration::emergencyDisabled() && hash_equals($this->flow()->revision(), (string)$marker['revision']) && time() < $marker['at'] + $config['session_max_age'];
            if ($valid) { $this->eligible($marker['uid']); return; }
        } catch (\Throwable) { /* Broken deployment fails closed for SOCA sessions only. */ }
        $this->store->remove('soca.auth');
        if (Configuration::emergencyDisabled()) { $this->store->purgeTickets(); }
        $this->userSession->logout();
    }
}
