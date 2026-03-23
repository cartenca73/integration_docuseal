<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Dashboard;

use OCA\DocuSeal\AppInfo\Application;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

class DocuSealWidget implements IAPIWidget, IIconWidget {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
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
		// Widget data is loaded via API
	}

	/**
	 * @return WidgetItems
	 */
	public function getItems(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		// This is handled by the API widget endpoint
		return new WidgetItems();
	}
}
