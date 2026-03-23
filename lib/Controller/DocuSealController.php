<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Controller;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Db\SignatureRequest;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCA\DocuSeal\Db\SignatureRequestSubmitter;
use OCA\DocuSeal\Db\SignatureRequestSubmitterMapper;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCA\DocuSeal\Service\UtilsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DocuSealController extends Controller {

	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		private DocuSealAPIService $docuSealAPIService,
		private UtilsService $utilsService,
		private SignatureRequestMapper $requestMapper,
		private SignatureRequestSubmitterMapper $submitterMapper,
		private LoggerInterface $logger,
		?string $userId,
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
	}

	/**
	 * Check if DocuSeal is configured
	 */
	#[NoAdminRequired]
	public function getInfo(): DataResponse {
		return new DataResponse([
			'connected' => $this->docuSealAPIService->isConfigured(),
		]);
	}

	/**
	 * Get available templates from DocuSeal
	 */
	#[NoAdminRequired]
	public function getTemplates(): DataResponse {
		if (!$this->docuSealAPIService->isConfigured()) {
			return new DataResponse(['error' => 'DocuSeal is not configured'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$templates = $this->docuSealAPIService->getTemplates();
			// Normalize response: DocuSeal may return {data: [...]} or [...]
			$data = $templates['data'] ?? $templates;
			return new DataResponse($data);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Send a PDF file directly for signing
	 */
	#[NoAdminRequired]
	public function signDirect(int $fileId): DataResponse {
		if (!$this->docuSealAPIService->isConfigured()) {
			return new DataResponse(['error' => 'DocuSeal is not configured'], Http::STATUS_BAD_REQUEST);
		}

		$targetEmails = $this->request->getParam('targetEmails', []);
		$targetUserIds = $this->request->getParam('targetUserIds', []);
		$sendEmail = $this->request->getParam('sendEmail', true);
		$subject = $this->request->getParam('subject');
		$message = $this->request->getParam('message');
		$expireAt = $this->request->getParam('expireAt'); // ISO 8601 date string

		if (empty($targetEmails) && empty($targetUserIds)) {
			return new DataResponse(['error' => 'No recipients specified'], Http::STATUS_BAD_REQUEST);
		}

		try {
			// Get the file
			$file = $this->utilsService->getFileForUser($this->userId, $fileId);
			$fileContent = $file->getContent();
			$fileName = $file->getName();
			$fileMime = $file->getMimeType();

			// Validate file type
			$allowedMimes = [
				'application/pdf',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/msword',
				'image/png',
				'image/jpeg',
			];
			if (!in_array($fileMime, $allowedMimes, true)) {
				return new DataResponse(
					['error' => 'Tipo di file non supportato. Usa PDF, DOCX, PNG o JPEG.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			// Build submitters list
			$submitters = $this->buildSubmittersList($targetEmails, $targetUserIds);

			if (empty($submitters)) {
				return new DataResponse(['error' => 'No valid recipients found'], Http::STATUS_BAD_REQUEST);
			}

			// Create submission via DocuSeal API
			$result = $this->docuSealAPIService->createDirectSubmission(
				$fileContent,
				$fileName,
				$submitters,
				$subject ?? 'Firma del documento: ' . $fileName,
				$message,
				$sendEmail,
				$expireAt,
			);

			// Save request to database
			$signatureRequest = $this->saveSignatureRequest(
				$fileId, $fileName, $file->getPath(), 'direct', $result, $submitters
			);

			return new DataResponse([
				'success' => true,
				'requestId' => $signatureRequest->getId(),
				'message' => 'Richiesta di firma inviata con successo',
			]);
		} catch (Exception $e) {
			$this->logger->error('Error creating direct signature request: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Send a file for signing using a template
	 */
	#[NoAdminRequired]
	public function signTemplate(): DataResponse {
		if (!$this->docuSealAPIService->isConfigured()) {
			return new DataResponse(['error' => 'DocuSeal is not configured'], Http::STATUS_BAD_REQUEST);
		}

		$templateId = (int)$this->request->getParam('templateId', 0);
		$fileId = (int)$this->request->getParam('fileId', 0);
		$targetEmails = $this->request->getParam('targetEmails', []);
		$targetUserIds = $this->request->getParam('targetUserIds', []);
		$sendEmail = $this->request->getParam('sendEmail', true);
		$subject = $this->request->getParam('subject');
		$message = $this->request->getParam('message');

		if ($templateId <= 0) {
			return new DataResponse(['error' => 'Template ID is required'], Http::STATUS_BAD_REQUEST);
		}

		if (empty($targetEmails) && empty($targetUserIds)) {
			return new DataResponse(['error' => 'No recipients specified'], Http::STATUS_BAD_REQUEST);
		}

		try {
			// Build submitters list
			$submitters = $this->buildSubmittersList($targetEmails, $targetUserIds);

			if (empty($submitters)) {
				return new DataResponse(['error' => 'No valid recipients found'], Http::STATUS_BAD_REQUEST);
			}

			// Create submission via DocuSeal API
			$result = $this->docuSealAPIService->createTemplateSubmission(
				$templateId,
				$submitters,
				$sendEmail,
				$subject,
				$message,
			);

			// Get file info if fileId provided
			$fileName = '';
			$filePath = '';
			if ($fileId > 0) {
				try {
					$file = $this->utilsService->getFileForUser($this->userId, $fileId);
					$fileName = $file->getName();
					$filePath = $file->getPath();
				} catch (Exception $e) {
					// File association is optional for template-based signing
				}
			}

			if ($fileName === '') {
				$fileName = 'Template #' . $templateId;
			}

			// Save request to database
			$signatureRequest = $this->saveSignatureRequest(
				$fileId, $fileName, $filePath, 'template', $result, $submitters, $templateId
			);

			return new DataResponse([
				'success' => true,
				'requestId' => $signatureRequest->getId(),
				'message' => 'Richiesta di firma inviata con successo',
			]);
		} catch (Exception $e) {
			$this->logger->error('Error creating template signature request: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get user's signature requests
	 */
	#[NoAdminRequired]
	public function getRequests(): DataResponse {
		$limit = (int)$this->request->getParam('limit', 50);
		$offset = (int)$this->request->getParam('offset', 0);

		try {
			$requests = $this->requestMapper->findByUserId($this->userId, $limit, $offset);
			$result = [];

			foreach ($requests as $request) {
				$submitters = $this->submitterMapper->findByRequestId($request->getId());
				$data = $request->jsonSerialize();
				$data['submitters'] = array_map(fn($s) => $s->jsonSerialize(), $submitters);
				$result[] = $data;
			}

			return new DataResponse($result);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a specific signature request detail
	 */
	#[NoAdminRequired]
	public function getRequest(int $id): DataResponse {
		try {
			$request = $this->requestMapper->find($id);

			// Verify ownership
			if ($request->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}

			$submitters = $this->submitterMapper->findByRequestId($request->getId());
			$data = $request->jsonSerialize();
			$data['submitters'] = array_map(fn($s) => $s->jsonSerialize(), $submitters);

			// Fetch latest status from DocuSeal if submission exists
			if ($request->getSubmissionId() !== null) {
				try {
					$submission = $this->docuSealAPIService->getSubmission($request->getSubmissionId());
					$data['docusealStatus'] = $submission['status'] ?? null;
					$data['docusealSubmitters'] = $submission['submitters'] ?? [];
					$data['documents'] = $submission['documents'] ?? [];
				} catch (Exception $e) {
					// Non-critical, we still have local data
				}
			}

			return new DataResponse($data);
		} catch (Exception $e) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get signature requests for a specific file
	 */
	#[NoAdminRequired]
	public function getFileRequests(int $fileId): DataResponse {
		try {
			$requests = $this->requestMapper->findByFileId($fileId, $this->userId);
			$result = [];

			foreach ($requests as $request) {
				$submitters = $this->submitterMapper->findByRequestId($request->getId());
				$data = $request->jsonSerialize();
				$data['submitters'] = array_map(fn($s) => $s->jsonSerialize(), $submitters);
				$result[] = $data;
			}

			return new DataResponse($result);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get embed URL for a signing request
	 */
	#[NoAdminRequired]
	public function getEmbedUrl(int $requestId): DataResponse {
		try {
			$request = $this->requestMapper->find($requestId);
			$submitters = $this->submitterMapper->findByRequestId($requestId);

			// Find a submitter that matches the current user
			$userInfo = $this->utilsService->getUserInfo($this->userId);
			$embedSrc = null;

			foreach ($submitters as $submitter) {
				if ($submitter->getNcUserId() === $this->userId
					|| ($userInfo !== null && $submitter->getEmail() === $userInfo['email'])) {
					$embedSrc = $submitter->getEmbedSrc();
					break;
				}
			}

			if ($embedSrc === null) {
				return new DataResponse(['error' => 'No signing URL available for this user'], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse([
				'embedSrc' => $embedSrc,
				'serverUrl' => $this->docuSealAPIService->getServerUrl(),
			]);
		} catch (Exception $e) {
			return new DataResponse(['error' => 'Request not found'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get template detail including field info (for preview)
	 */
	#[NoAdminRequired]
	public function getTemplateDetail(int $templateId): DataResponse {
		if (!$this->docuSealAPIService->isConfigured()) {
			return new DataResponse(['error' => 'DocuSeal is not configured'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$template = $this->docuSealAPIService->getTemplate($templateId);
			return new DataResponse($template);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Resend a reminder to a specific submitter
	 */
	#[NoAdminRequired]
	public function resendReminder(int $id, int $submitterId): DataResponse {
		try {
			$request = $this->requestMapper->find($id);
			if ($request->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}

			$submitter = $this->submitterMapper->find($submitterId);
			if ($submitter->getRequestId() !== $id) {
				return new DataResponse(['error' => 'Submitter not found'], Http::STATUS_NOT_FOUND);
			}

			if ($submitter->getStatus() === 'completed') {
				return new DataResponse(['error' => 'Already completed'], Http::STATUS_BAD_REQUEST);
			}

			// Use DocuSeal API to resend
			$dsSubmitterId = $submitter->getDocusealSubmitterId();
			if ($dsSubmitterId !== null) {
				$this->docuSealAPIService->request('PUT', '/submitters/' . $dsSubmitterId, [
					'send_email' => true,
				]);
			}

			return new DataResponse(['success' => true, 'message' => 'Promemoria inviato']);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Cancel a pending signature request
	 */
	#[NoAdminRequired]
	public function cancelRequest(int $id): DataResponse {
		try {
			$request = $this->requestMapper->find($id);
			if ($request->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}

			if ($request->getStatus() === 'completed') {
				return new DataResponse(['error' => 'Cannot cancel completed request'], Http::STATUS_BAD_REQUEST);
			}

			// Archive the submission in DocuSeal
			if ($request->getSubmissionId() !== null) {
				try {
					$this->docuSealAPIService->request('DELETE', '/submissions/' . $request->getSubmissionId());
				} catch (Exception $e) {
					// Non-critical if DocuSeal archive fails
					$this->logger->warning('Could not archive DocuSeal submission: ' . $e->getMessage());
				}
			}

			$request->setStatus('cancelled');
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			return new DataResponse(['success' => true, 'message' => 'Richiesta annullata']);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get audit trail for a request
	 */
	#[NoAdminRequired]
	public function getAuditTrail(int $id): DataResponse {
		try {
			$request = $this->requestMapper->find($id);
			if ($request->getUserId() !== $this->userId) {
				return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
			}

			if ($request->getSubmissionId() === null) {
				return new DataResponse(['error' => 'No submission found'], Http::STATUS_NOT_FOUND);
			}

			$submission = $this->docuSealAPIService->getSubmission($request->getSubmissionId());

			return new DataResponse([
				'auditLogUrl' => $submission['audit_log_url'] ?? null,
				'events' => $submission['submission_events'] ?? [],
				'submitters' => array_map(function ($s) {
					return [
						'email' => $s['email'] ?? '',
						'name' => $s['name'] ?? '',
						'status' => $s['status'] ?? '',
						'sentAt' => $s['sent_at'] ?? null,
						'openedAt' => $s['opened_at'] ?? null,
						'completedAt' => $s['completed_at'] ?? null,
						'declinedAt' => $s['declined_at'] ?? null,
					];
				}, $submission['submitters'] ?? []),
			]);
		} catch (Exception $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Build a list of submitters from email addresses and Nextcloud user IDs
	 */
	private function buildSubmittersList(array $targetEmails, array $targetUserIds): array {
		$submitters = [];
		$roleIndex = 1;

		// Add Nextcloud users
		foreach ($targetUserIds as $uid) {
			$userInfo = $this->utilsService->getUserInfo($uid);
			if ($userInfo !== null && !empty($userInfo['email'])) {
				$submitters[] = [
					'email' => $userInfo['email'],
					'name' => $userInfo['name'],
					'role' => 'First Party',
					'nc_user_id' => $uid,
				];
			}
		}

		// Add external email addresses
		foreach ($targetEmails as $email) {
			$email = trim($email);
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$submitters[] = [
					'email' => $email,
					'name' => '',
					'role' => 'First Party',
					'nc_user_id' => null,
				];
			}
		}

		return $submitters;
	}

	/**
	 * Save a signature request and its submitters to the database
	 */
	private function saveSignatureRequest(
		int $fileId,
		string $fileName,
		string $filePath,
		string $signType,
		array $apiResult,
		array $submitters,
		?int $templateId = null,
	): SignatureRequest {
		$now = time();

		// The API result for direct submissions is an array of submitter objects
		// For template submissions, it's also an array of submitter objects
		$submissionId = null;
		if (!empty($apiResult)) {
			// Get submission_id from the first submitter result
			$firstResult = is_array($apiResult) && isset($apiResult[0]) ? $apiResult[0] : $apiResult;
			$submissionId = $firstResult['submission_id'] ?? null;
		}

		// Create the signature request
		$request = new SignatureRequest();
		$request->setUserId($this->userId);
		$request->setFileId($fileId);
		$request->setFileName($fileName);
		$request->setFilePath($filePath);
		$request->setSubmissionId($submissionId);
		$request->setTemplateId($templateId);
		$request->setSignType($signType);
		$request->setStatus('sent');
		$request->setCreatedAt($now);
		$request->setUpdatedAt($now);
		$request = $this->requestMapper->insert($request);

		// Save submitters
		$apiSubmitters = is_array($apiResult) && isset($apiResult[0]) ? $apiResult : [$apiResult];

		foreach ($submitters as $i => $submitterData) {
			$sub = new SignatureRequestSubmitter();
			$sub->setRequestId($request->getId());
			$sub->setEmail($submitterData['email']);
			$sub->setName($submitterData['name'] ?? '');
			$sub->setRole($submitterData['role'] ?? 'First Party');
			$sub->setNcUserId($submitterData['nc_user_id'] ?? null);
			$sub->setStatus('sent');
			$sub->setCreatedAt($now);

			// Match with API response to get DocuSeal submitter ID and embed URL
			if (isset($apiSubmitters[$i])) {
				$apiSub = $apiSubmitters[$i];
				$sub->setDocusealSubmitterId($apiSub['id'] ?? null);
				$sub->setEmbedSrc($apiSub['embed_src'] ?? null);
				$sub->setStatus($apiSub['status'] ?? 'sent');
			}

			$this->submitterMapper->insert($sub);
		}

		return $request;
	}
}
