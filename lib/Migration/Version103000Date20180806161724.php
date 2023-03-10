<?php

namespace OCA\TemplateRepo\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version103000Date20180806161724 extends SimpleMigrationStep {
	/** @var IDBConnection */
	private $connection;

	/** @var array */
	private $applicableData = [];

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// copy data
		if ($schema->hasTable('template_repo_applicable')) {
			$query = $this->connection->getQueryBuilder();
			$query->select(['folder_id', 'permissions', 'group_id'])
				->from('template_repo_applicable');
			$result = $query->executeQuery();
			$this->applicableData = $result->fetchAll(\PDO::FETCH_ASSOC);
		}
	}

	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		
		if ($schema->hasTable('template_repo_applicable')) {
			$schema->dropTable('template_repo_applicable');
		}

		return $schema;
	}

	/**
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		if (count($this->applicableData)) {
			$query = $this->connection->getQueryBuilder();
			$query->insert('template_repo_groups')
				->values([
					'folder_id' => $query->createParameter('folder'),
					'group_id' => $query->createParameter('group'),
					'permissions' => $query->createParameter('permissions')
				]);

			foreach ($this->applicableData as $data) {
				$query->setParameter('folder', $data['folder_id']);
				$query->setParameter('group', $data['group_id']);
				$query->setParameter('permissions', $data['permissions']);

				$query->executeStatement();
			}
		}
	}
}
