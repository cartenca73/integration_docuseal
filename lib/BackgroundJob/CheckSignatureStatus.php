<?php

declare(strict_types=1);

namespace OCA\DocuSeal\BackgroundJob;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Db\SignatureRequest;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCA\DocuSeal\Db\SignatureRequestSubmitterMapper;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCA\DocuSeal\Service\UtilsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class CheckSignatureStatus extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private DocuSealAPIService $docuSealAPIService,
		private SignatureRequestMapper $requestMapper,
		private SignatureRequestSubmitterMapper $submitterMapper,
		private UtilsService $utilsService,
		private INotificationManager $notificationManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		// Run every 15 minutes
		$this->setInterval(15 * 60);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run(mixed $argument): void {
		if (!$this->docuSealAPIService->isConfigured()) {
			return;
		}

		$pendingRequests = $this->requestMapper->findPending();

		foreach ($pendingRequests as $request) {
			try {
				$this->checkRequest($request);
			} catch (Exception $e) {
				$this->logger->error('Error checking signature status for request ' . $request->getId() . ': ' . $e->getMessage(), [
					'app' => Application::APP_ID,
				]);
			}
		}
	}

	private function checkRequest(SignatureRequest $request): void {
		$submissionId = $request->getSubmissionId();
		if ($submissionId === null) {
			return;
		}

		$submission = $this->docuSealAPIService->getSubmission($submissionId);
		$remoteStatus = $submission['status'] ?? null;

		if ($remoteStatus === null) {
			return;
		}

		// Update submitters
		$localSubmitters = $this->submitterMapper->findByRequestId($request->getId());
		$remoteSubmitters = $submission['submitters'] ?? [];

		foreach ($localSubmitters as $localSub) {
			foreach ($remoteSubmitters as $remoteSub) {
				if ($localSub->getDocusealSubmitterId() === ($remoteSub['id'] ?? null)) {
					$newStatus = $remoteSub['status'] ?? $localSub->getStatus();
					if ($newStatus !== $localSub->getStatus()) {
						$localSub->setStatus($newStatus);
						if ($newStatus === 'completed' && $localSub->getCompletedAt() === null) {
							$localSub->setCompletedAt(time());

							// Send notification for individual completion
							$this->sendNotification(
								$request->getUserId(),
								'signature_completed',
								[
									'requestId' => $request->getId(),
									'fileName' => $request->getFileName(),
									'signerEmail' => $localSub->getEmail(),
									'signerName' => $localSub->getName(),
								]
							);
						}
						$this->submitterMapper->update($localSub);
					}
					break;
				}
			}
		}

		// Update request status
		if ($remoteStatus !== $request->getStatus()) {
			$oldStatus = $request->getStatus();
			$request->setStatus($remoteStatus);
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			// If newly completed, download signed document
			if ($remoteStatus === 'completed' && $oldStatus !== 'completed') {
				$this->downloadSignedDocument($request, $submission);

				$this->sendNotification(
					$request->getUserId(),
					'all_signatures_completed',
					[
						'requestId' => $request->getId(),
						'fileName' => $request->getFileName(),
					]
				);
			}
		}
	}

	private function downloadSignedDocument(SignatureRequest $request, array $submission): void {
		$documentUrl = $submission['combined_document_url'] ?? null;

		if ($documentUrl === null && !empty($submission['documents'])) {
			$documentUrl = $submission['documents'][0]['url'] ?? null;
		}

		if ($documentUrl === null && !empty($submission['submitters'])) {
			foreach ($submission['submitters'] as $sub) {
				if (!empty($sub['documents'])) {
					$documentUrl = $sub['documents'][0]['url'] ?? null;
					if ($documentUrl !== null) {
						break;
					}
				}
			}
		}

		if ($documentUrl === null) {
			return;
		}

		try {
			$content = $this->docuSealAPIService->downloadDocument($documentUrl);
			$signedFileId = $this->utilsService->saveSignedDocument(
				$request->getUserId(),
				$content,
				$request->getFilePath(),
				$request->getFileName(),
			);
			$request->setSignedFileId($signedFileId);
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);
		} catch (Exception $e) {
			$this->logger->error('Error downloading signed document: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
		}
	}

	private function sendNotification(string $userId, string $subject, array $params): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('signature_request', (string)($params['requestId'] ?? '0'))
				->setSubject($subject, $params);

			$this->notificationManager->notify($notification);
		} catch (Exception $e) {
			$this->logger->error('Error sending notification: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
		}
	}
}
