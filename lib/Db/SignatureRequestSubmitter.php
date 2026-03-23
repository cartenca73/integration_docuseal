<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getRequestId()
 * @method void setRequestId(int $requestId)
 * @method int|null getDocusealSubmitterId()
 * @method void setDocusealSubmitterId(?int $docusealSubmitterId)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getRole()
 * @method void setRole(string $role)
 * @method string|null getNcUserId()
 * @method void setNcUserId(?string $ncUserId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getEmbedSrc()
 * @method void setEmbedSrc(?string $embedSrc)
 * @method int|null getCompletedAt()
 * @method void setCompletedAt(?int $completedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class SignatureRequestSubmitter extends Entity {
	protected int $requestId = 0;
	protected ?int $docusealSubmitterId = null;
	protected string $email = '';
	protected string $name = '';
	protected string $role = 'First Party';
	protected ?string $ncUserId = null;
	protected string $status = 'pending'; // pending, sent, opened, completed, declined
	protected ?string $embedSrc = null;
	protected ?int $completedAt = null;
	protected int $createdAt = 0;

	public function __construct() {
		$this->addType('requestId', 'integer');
		$this->addType('docusealSubmitterId', 'integer');
		$this->addType('email', 'string');
		$this->addType('name', 'string');
		$this->addType('role', 'string');
		$this->addType('ncUserId', 'string');
		$this->addType('status', 'string');
		$this->addType('embedSrc', 'string');
		$this->addType('completedAt', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'requestId' => $this->requestId,
			'docusealSubmitterId' => $this->docusealSubmitterId,
			'email' => $this->email,
			'name' => $this->name,
			'role' => $this->role,
			'ncUserId' => $this->ncUserId,
			'status' => $this->status,
			'embedSrc' => $this->embedSrc,
			'completedAt' => $this->completedAt,
			'createdAt' => $this->createdAt,
		];
	}
}
