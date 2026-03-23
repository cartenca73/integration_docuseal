<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Service;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class DocuSealAPIService {

	private const ENCRYPTED_KEYS = ['api_key'];

	public function __construct(
		private IClientService $clientService,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get DocuSeal server base URL
	 */
	public function getServerUrl(): string {
		return rtrim(
			$this->appConfig->getValueString(Application::APP_ID, 'server_url', ''),
			'/'
		);
	}

	/**
	 * Get decrypted API key
	 */
	public function getApiKey(): string {
		$encrypted = $this->appConfig->getValueString(Application::APP_ID, 'api_key', '');
		if ($encrypted === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($encrypted);
		} catch (Exception $e) {
			// Might be stored unencrypted (first install)
			return $encrypted;
		}
	}

	/**
	 * Check if the integration is configured
	 */
	public function isConfigured(): bool {
		return $this->getServerUrl() !== '' && $this->getApiKey() !== '';
	}

	/**
	 * Make an API request to DocuSeal
	 */
	public function request(string $method, string $endpoint, array $params = []): array {
		$serverUrl = $this->getServerUrl();
		$apiKey = $this->getApiKey();

		if ($serverUrl === '' || $apiKey === '') {
			throw new Exception('DocuSeal is not configured');
		}

		$url = $serverUrl . '/api' . $endpoint;
		$client = $this->clientService->newClient();

		$options = [
			'headers' => [
				'X-Auth-Token' => $apiKey,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
			'timeout' => 30,
		];

		try {
			if ($method === 'GET') {
				if (!empty($params)) {
					$options['query'] = $params;
				}
				$response = $client->get($url, $options);
			} elseif ($method === 'POST') {
				$options['body'] = json_encode($params);
				$response = $client->post($url, $options);
			} elseif ($method === 'PUT') {
				$options['body'] = json_encode($params);
				$response = $client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $client->delete($url, $options);
			} else {
				throw new Exception('Unsupported HTTP method: ' . $method);
			}

			$body = $response->getBody();
			return json_decode($body, true) ?? [];
		} catch (Exception $e) {
			$this->logger->error('DocuSeal API error: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'method' => $method,
				'endpoint' => $endpoint,
			]);
			throw $e;
		}
	}

	/**
	 * Upload a file directly and create a submission
	 *
	 * Uses POST /submissions/pdf to create from PDF with field tags
	 */
	public function createDirectSubmission(
		string $fileContent,
		string $fileName,
		array $submitters,
		?string $subject = null,
		?string $message = null,
		bool $sendEmail = true,
		?string $expireAt = null,
	): array {
		$serverUrl = $this->getServerUrl();
		$apiKey = $this->getApiKey();

		if ($serverUrl === '' || $apiKey === '') {
			throw new Exception('DocuSeal is not configured');
		}

		// Determine endpoint based on file extension
		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$endpointMap = [
			'pdf' => '/api/submissions/pdf',
			'docx' => '/api/submissions/docx',
			'doc' => '/api/submissions/docx',
			'png' => '/api/submissions/pdf',
			'jpg' => '/api/submissions/pdf',
			'jpeg' => '/api/submissions/pdf',
		];
		$endpoint = $endpointMap[$ext] ?? '/api/submissions/pdf';

		$url = $serverUrl . $endpoint;
		$client = $this->clientService->newClient();

		// Build multipart form data
		$multipart = [
			[
				'name' => 'file',
				'content' => $fileContent,
				'filename' => $fileName,
			],
		];

		// Add submitters
		foreach ($submitters as $i => $submitter) {
			$multipart[] = [
				'name' => "submitters[$i][email]",
				'content' => $submitter['email'],
			];
			if (!empty($submitter['name'])) {
				$multipart[] = [
					'name' => "submitters[$i][name]",
					'content' => $submitter['name'],
				];
			}
			if (!empty($submitter['role'])) {
				$multipart[] = [
					'name' => "submitters[$i][role]",
					'content' => $submitter['role'],
				];
			}
			$multipart[] = [
				'name' => "submitters[$i][send_email]",
				'content' => $sendEmail ? 'true' : 'false',
			];
		}

		// Add message if provided
		if ($subject !== null) {
			$multipart[] = [
				'name' => 'message[subject]',
				'content' => $subject,
			];
		}
		if ($message !== null) {
			$multipart[] = [
				'name' => 'message[body]',
				'content' => $message,
			];
		}

		if ($expireAt !== null) {
			$multipart[] = [
				'name' => 'expire_at',
				'content' => $expireAt,
			];
		}

		try {
			$response = $client->post($url, [
				'headers' => [
					'X-Auth-Token' => $apiKey,
					'Accept' => 'application/json',
				],
				'multipart' => $multipart,
				'timeout' => 60,
			]);

			$body = $response->getBody();
			return json_decode($body, true) ?? [];
		} catch (Exception $e) {
			$this->logger->error('DocuSeal direct submission error: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
			throw $e;
		}
	}

	/**
	 * Create a submission from an existing template
	 */
	public function createTemplateSubmission(
		int $templateId,
		array $submitters,
		bool $sendEmail = true,
		?string $subject = null,
		?string $message = null,
		?string $expireAt = null,
	): array {
		$params = [
			'template_id' => $templateId,
			'send_email' => $sendEmail,
			'submitters' => $submitters,
		];

		if ($subject !== null || $message !== null) {
			$params['message'] = [];
			if ($subject !== null) {
				$params['message']['subject'] = $subject;
			}
			if ($message !== null) {
				$params['message']['body'] = $message;
			}
		}

		if ($expireAt !== null) {
			$params['expire_at'] = $expireAt;
		}

		return $this->request('POST', '/submissions', $params);
	}

	/**
	 * Get a submission's details
	 */
	public function getSubmission(int $submissionId): array {
		return $this->request('GET', '/submissions/' . $submissionId);
	}

	/**
	 * List all templates
	 */
	public function getTemplates(?int $limit = 100, ?string $folder = null): array {
		$params = [];
		if ($limit !== null) {
			$params['limit'] = $limit;
		}
		if ($folder !== null) {
			$params['template_folder'] = $folder;
		}
		return $this->request('GET', '/templates', $params);
	}

	/**
	 * Get a single template
	 */
	public function getTemplate(int $templateId): array {
		return $this->request('GET', '/templates/' . $templateId);
	}

	/**
	 * Download a document from a URL
	 */
	public function downloadDocument(string $url): string {
		$client = $this->clientService->newClient();
		$response = $client->get($url, [
			'headers' => [
				'X-Auth-Token' => $this->getApiKey(),
			],
			'timeout' => 120,
		]);
		return $response->getBody();
	}

	/**
	 * Test the connection to DocuSeal
	 */
	public function testConnection(): array {
		try {
			$result = $this->getTemplates(1);
			return [
				'success' => true,
				'message' => 'Connection successful',
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}
}
