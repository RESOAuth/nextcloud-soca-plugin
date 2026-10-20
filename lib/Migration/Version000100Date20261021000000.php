<?php
declare(strict_types=1);
namespace OCA\Soca\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000100Date20261021000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        $schema = $schemaClosure();
        if (!$schema->hasTable('soca_tickets')) {
            $table = $schema->createTable('soca_tickets');
            foreach (['state_hash','browser_hash','revision'] as $field) { $table->addColumn($field, 'string', ['notnull' => true, 'length' => 64]); }
            $table->addColumn('expires', 'integer', ['notnull' => true]);
            $table->setPrimaryKey(['state_hash']); $table->addIndex(['expires'], 'soca_ticket_expiry');
        }
        if (!$schema->hasTable('soca_links')) {
            $table = $schema->createTable('soca_links');
            $table->addColumn('identity_hash', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('user_id', 'string', ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', 'integer', ['notnull' => true]);
            $table->setPrimaryKey(['identity_hash']);
        }
        return $schema;
    }
}
