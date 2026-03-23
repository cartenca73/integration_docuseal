<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<SignatureRequestSubmitter>
 */
class SignatureRequestSubmitterMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'docuseal_submitters', SignatureRequestSubmitter::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function find(int $id): SignatureRequestSubmitter {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function findByDocuSealId(int $docusealSubmitterId): SignatureRequestSubmitter {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('docuseal_submitter_id', $qb->createNamedParameter($docusealSubmitterId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return SignatureRequestSubmitter[]
	 */
	public function findByRequestId(int $requestId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('request_id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * @return SignatureRequestSubmitter[]
	 */
	public function findByEmail(string $email): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('email', $qb->createNamedParameter($email)))
			->orderBy('created_at', 'DESC');
		return $this->findEntities($qb);
	}
}
