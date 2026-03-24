<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Service;

use OCA\DocuSeal\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Security\ICrypto;

class JwtService {

	public function __construct(
		private DocuSealAPIService $docuSealAPIService,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
	) {
	}

	/**
	 * Generate a JWT token for the DocuSeal template builder
	 */
	public function generateBuilderToken(
		string $userEmail,
		string $templateName,
		array $documentUrls,
	): string {
		$apiKey = $this->docuSealAPIService->getApiKey();
		if ($apiKey === '') {
			throw new \Exception('DocuSeal API key not configured');
		}

		$header = [
			'alg' => 'HS256',
			'typ' => 'JWT',
		];

		$payload = [
			'user_email' => $this->getDocuSealUserEmail(),
			'integration_email' => $userEmail,
			'name' => $templateName,
			'document_urls' => $documentUrls,
			'iat' => time(),
			'exp' => time() + 3600,
		];

		$headerEncoded = $this->base64UrlEncode(json_encode($header));
		$payloadEncoded = $this->base64UrlEncode(json_encode($payload));

		$signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $apiKey, true);
		$signatureEncoded = $this->base64UrlEncode($signature);

		return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
	}

	/**
	 * Generate a short-lived HMAC token for file download authentication
	 */
	public function generateFileDownloadToken(int $fileId, string $userId): string {
		$apiKey = $this->docuSealAPIService->getApiKey();
		$expiry = time() + 600; // 10 minutes
		$data = $fileId . ':' . $userId . ':' . $expiry;
		$hmac = hash_hmac('sha256', $data, $apiKey);
		return base64_encode($data . ':' . $hmac);
	}

	/**
	 * Validate a file download token and return [fileId, userId] or null
	 */
	public function validateFileDownloadToken(string $token): ?array {
		$apiKey = $this->docuSealAPIService->getApiKey();
		$decoded = base64_decode($token, true);
		if ($decoded === false) {
			return null;
		}

		$parts = explode(':', $decoded);
		if (count($parts) !== 4) {
			return null;
		}

		[$fileId, $userId, $expiry, $hmac] = $parts;
		$data = $fileId . ':' . $userId . ':' . $expiry;
		$expectedHmac = hash_hmac('sha256', $data, $apiKey);

		if (!hash_equals($expectedHmac, $hmac)) {
			return null;
		}

		if ((int)$expiry < time()) {
			return null;
		}

		return [
			'fileId' => (int)$fileId,
			'userId' => $userId,
		];
	}

	/**
	 * Get the DocuSeal admin user email (for builder token)
	 */
	private function getDocuSealUserEmail(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			'docuseal_user_email',
			'c.tenca@ce4u.it'
		);
	}

	private function base64UrlEncode(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
}
