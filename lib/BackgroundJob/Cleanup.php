<?php
declare(strict_types=1);
namespace OCA\Soca\BackgroundJob;
final class Cleanup extends \OCP\BackgroundJob\TimedJob
{
    public function __construct(\OCP\AppFramework\Utility\ITimeFactory $time, private \OCP\IDBConnection $db) { parent::__construct($time); $this->setInterval(300); }
    protected function run($argument): void
    {
        $q = $this->db->getQueryBuilder();
        $q->delete('soca_tickets')->where($q->expr()->lt('expires', $q->createNamedParameter(time())))->executeStatement();
    }
}
