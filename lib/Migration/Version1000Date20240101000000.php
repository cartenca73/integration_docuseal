<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20240101000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Signature requests table
		if (!$schema->hasTable('docuseal_requests')) {
			$table = $schema->createTable('docuseal_requests');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('file_name', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('file_path', Types::STRING, [
				'notnull' => true,
				'length' => 1024,
				'default' => '',
			]);
			$table->addColumn('submission_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('template_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('sign_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'direct',
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('signed_file_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'docuseal_req_uid');
			$table->addIndex(['file_id'], 'docuseal_req_fid');
			$table->addIndex(['submission_id'], 'docuseal_req_sid');
			$table->addIndex(['status'], 'docuseal_req_status');
		}

		// Submitters table
		if (!$schema->hasTable('docuseal_submitters')) {
			$table = $schema->createTable('docuseal_submitters');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
			]);
			$table->addColumn('request_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('docuseal_submitter_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('email', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 256,
				'default' => '',
			]);
			$table->addColumn('role', Types::STRING, [
				'notnull' => true,
				'length' => 128,
				'default' => 'First Party',
			]);
			$table->addColumn('nc_user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('embed_src', Types::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->addColumn('completed_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['request_id'], 'docuseal_sub_rid');
			$table->addIndex(['docuseal_submitter_id'], 'docuseal_sub_dsid');
			$table->addIndex(['email'], 'docuseal_sub_email');
		}

		return $schema;
	}
}
