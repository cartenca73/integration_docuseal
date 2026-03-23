<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Activity;

use OCA\DocuSeal\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider as IActivityProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class Provider implements IActivityProvider {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function parse(string $language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException();
		}

		$params = $event->getSubjectParameters();
		$fileName = $params['fileName'] ?? 'documento';
		$signerName = $params['signerName'] ?? $params['signerEmail'] ?? '';

		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			)
		);

		switch ($event->getSubject()) {
			case 'signature_requested':
				$event->setParsedSubject(
					$this->l10n->t('Hai richiesto la firma per "%s"', [$fileName])
				);
				break;
			case 'signature_completed':
				$event->setParsedSubject(
					$this->l10n->t('%s ha firmato "%s"', [$signerName, $fileName])
				);
				break;
			case 'signature_declined':
				$event->setParsedSubject(
					$this->l10n->t('%s ha rifiutato di firmare "%s"', [$signerName, $fileName])
				);
				break;
			case 'all_signatures_completed':
				$event->setParsedSubject(
					$this->l10n->t('Tutte le firme completate per "%s"', [$fileName])
				);
				break;
			case 'signature_cancelled':
				$event->setParsedSubject(
					$this->l10n->t('Richiesta di firma annullata per "%s"', [$fileName])
				);
				break;
			default:
				throw new \InvalidArgumentException('Unknown subject: ' . $event->getSubject());
		}

		return $event;
	}
}
