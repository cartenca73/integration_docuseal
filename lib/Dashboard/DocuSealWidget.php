<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Dashboard;

use OCA\DocuSeal\AppInfo\Application;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IIconWidget;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IL10N;
use OCP\IURLGenerator;

class DocuSealWidget implements IAPIWidget, IIconWidget {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private SignatureRequestMapper $requestMapper,
	) {
	}

	public function getId(): string {
		return Application::APP_ID . '-widget';
	}

	public function getTitle(): string {
		return $this->l10n->t('Firme DocuSeal');
	}

	public function getOrder(): int {
		return 10;
	}

	public function getIconClass(): string {
		return 'icon-docuseal';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
	}

	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$requests = $this->requestMapper->findByUserId($userId, $limit);
		$items = [];
		foreach ($requests as $request) {
			$items[] = new WidgetItem(
				$request->getFileName(),
				$request->getStatus(),
				$request->getFileId() > 0
					? $this->urlGenerator->getAbsoluteURL('/f/' . $request->getFileId())
					: '',
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
				),
				(string)$request->getCreatedAt(),
			);
		}
		return $items;
	}
}
