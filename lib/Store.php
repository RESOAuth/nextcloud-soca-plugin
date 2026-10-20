<?php
declare(strict_types=1);
namespace OCA\Soca;

use OCP\IDBConnection;
use OCP\ISession;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use RESOAuth\SocaHost\Host;

final class Store implements Host
{
    public function __construct(public IDBConnection $db, private ISession $session, private IConfig $config, private LoggerInterface $logger) {}
    public function get(string $key): mixed { return $this->session->get($key); }
    public function set(string $key, mixed $value): void { $this->session->set($key, $value); }
    public function remove(string $key): void { $this->session->remove($key); }
    public function putTicket(string $stateHash, string $browserHash, string $revision, int $expires): void
    {
        $q = $this->db->getQueryBuilder();
        $q->delete('soca_tickets')->where($q->expr()->lt('expires', $q->createNamedParameter(time())))->executeStatement();
        $q = $this->db->getQueryBuilder();
        $q->insert('soca_tickets')->values(['state_hash' => $q->createNamedParameter($stateHash), 'browser_hash' => $q->createNamedParameter($browserHash), 'revision' => $q->createNamedParameter($revision), 'expires' => $q->createNamedParameter($expires)])->executeStatement();
    }
    public function consumeTicket(string $stateHash, string $browserHash, string $revision, int $now): bool
    {
        $q = $this->db->getQueryBuilder();
        return $q->delete('soca_tickets')->where($q->expr()->eq('state_hash', $q->createNamedParameter($stateHash)), $q->expr()->eq('browser_hash', $q->createNamedParameter($browserHash)), $q->expr()->eq('revision', $q->createNamedParameter($revision)), $q->expr()->gt('expires', $q->createNamedParameter($now)))->executeStatement() === 1;
    }
    public function purgeTickets(): void { $this->db->getQueryBuilder()->delete('soca_tickets')->executeStatement(); }
    public function evidence(): array { return json_decode($this->config->getAppValue('soca', 'evidence', '{}'), true, 16, JSON_THROW_ON_ERROR); }
    public function saveEvidence(array $evidence): void { $this->config->setAppValue('soca', 'evidence', json_encode($evidence, JSON_THROW_ON_ERROR)); }
    public function audit(string $event, string $reference): void { $this->logger->notice('SOCA ' . $event, ['app' => 'soca', 'reference' => $reference]); }
    public function linkedUid(string $hash): ?string
    {
        $q = $this->db->getQueryBuilder();
        $result = $q->select('user_id')->from('soca_links')->where($q->expr()->eq('identity_hash', $q->createNamedParameter($hash)))->executeQuery();
        $uid = $result->fetchOne(); $result->closeCursor();
        return $uid === false ? null : (string)$uid;
    }
    public function link(string $hash, string $uid): void
    {
        $q = $this->db->getQueryBuilder();
        $q->insert('soca_links')->values(['identity_hash' => $q->createNamedParameter($hash), 'user_id' => $q->createNamedParameter($uid), 'created_at' => $q->createNamedParameter(time())])->executeStatement();
        $this->audit('identity_linked', $hash);
    }
    public function unlink(string $hash): void
    {
        $q = $this->db->getQueryBuilder();
        $q->delete('soca_links')->where($q->expr()->eq('identity_hash', $q->createNamedParameter($hash)))->executeStatement();
        $this->purgeTickets(); $this->audit('identity_unlinked', $hash);
    }
}
