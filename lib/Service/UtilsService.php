<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Service;

use OCA\DocuSeal\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UtilsService {

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get a file node for a given user and file ID
	 *
	 * @throws NotFoundException
	 */
	public function getFileForUser(string $userId, int $fileId): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$files = $userFolder->getById($fileId);

		if (empty($files)) {
			throw new NotFoundException('File not found');
		}

		$file = $files[0];
		if (!($file instanceof File)) {
			throw new NotFoundException('Not a file');
		}

		return $file;
	}

	/**
	 * Check if user has access to a file
	 */
	public function userHasAccessTo(string $userId, int $fileId): bool {
		try {
			$this->getFileForUser($userId, $fileId);
			return true;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * Get user display name and email
	 *
	 * @return array{name: string, email: string}|null
	 */
	public function getUserInfo(string $userId): ?array {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return null;
		}
		return [
			'name' => $user->getDisplayName(),
			'email' => $user->getEMailAddress() ?? '',
		];
	}

	/**
	 * Save signed document back to Nextcloud
	 *
	 * @return int File ID of the saved file
	 */
	public function saveSignedDocument(
		string $userId,
		string $content,
		string $originalPath,
		string $originalName,
	): int {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		// Build the signed file name
		$pathInfo = pathinfo($originalName);
		$signedName = ($pathInfo['filename'] ?? $originalName) . '_signed.' . ($pathInfo['extension'] ?? 'pdf');

		// Determine target directory (same as original file)
		$targetDir = dirname($originalPath);
		if ($targetDir === '.' || $targetDir === '') {
			$targetDir = '/';
		}

		try {
			$folder = $userFolder->get($targetDir);
		} catch (NotFoundException $e) {
			$folder = $userFolder;
		}

		// Avoid overwriting - add number suffix if needed
		$finalName = $signedName;
		$counter = 1;
		while ($folder->nodeExists($finalName)) {
			$finalName = ($pathInfo['filename'] ?? $originalName)
				. '_signed_' . $counter . '.' . ($pathInfo['extension'] ?? 'pdf');
			$counter++;
		}

		$newFile = $folder->newFile($finalName, $content);

		$this->logger->info('Signed document saved: ' . $finalName, [
			'app' => Application::APP_ID,
			'userId' => $userId,
		]);

		return $newFile->getId();
	}
}
