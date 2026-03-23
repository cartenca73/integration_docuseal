<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Dashboard;

use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCA\DocuSeal\Db\SignatureRequestSubmitterMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class DocuSealWidgetAPI extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private SignatureRequestMapper $requestMapper,
		private SignatureRequestSubmitterMapper $submitterMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function getWidgetItems(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		$requests = $this->requestMapper->findByUserId($this->userId, 10);
		$result = [];

		foreach ($requests as $request) {
			$submitters = $this->submitterMapper->findByRequestId($request->getId());
			$totalSubmitters = count($submitters);
			$completedSubmitters = 0;
			foreach ($submitters as $sub) {
				if ($sub->getStatus() === 'completed') {
					$completedSubmitters++;
				}
			}

			$result[] = [
				'id' => $request->getId(),
				'fileName' => $request->getFileName(),
				'status' => $request->getStatus(),
				'progress' => $totalSubmitters > 0 ? $completedSubmitters . '/' . $totalSubmitters : '0/0',
				'createdAt' => $request->getCreatedAt(),
				'fileId' => $request->getFileId(),
			];
		}

		return new DataResponse($result);
	}
}
