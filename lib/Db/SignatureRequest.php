<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 * @method string getFilePath()
 * @method void setFilePath(string $filePath)
 * @method int|null getSubmissionId()
 * @method void setSubmissionId(?int $submissionId)
 * @method int|null getTemplateId()
 * @method void setTemplateId(?int $templateId)
 * @method string getSignType()
 * @method void setSignType(string $signType)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int|null getSignedFileId()
 * @method void setSignedFileId(?int $signedFileId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class SignatureRequest extends Entity {
	protected string $userId = '';
	protected int $fileId = 0;
	protected string $fileName = '';
	protected string $filePath = '';
	protected ?int $submissionId = null;
	protected ?int $templateId = null;
	protected string $signType = 'direct'; // 'direct' or 'template'
	protected string $status = 'pending'; // pending, sent, completed, declined, expired
	protected ?int $signedFileId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('fileName', 'string');
		$this->addType('filePath', 'string');
		$this->addType('submissionId', 'integer');
		$this->addType('templateId', 'integer');
		$this->addType('signType', 'string');
		$this->addType('status', 'string');
		$this->addType('signedFileId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'fileId' => $this->fileId,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath,
			'submissionId' => $this->submissionId,
			'templateId' => $this->templateId,
			'signType' => $this->signType,
			'status' => $this->status,
			'signedFileId' => $this->signedFileId,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
