<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Notification;

use OCA\DocuSeal\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('DocuSeal');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l = $this->factory->get(Application::APP_ID, $languageCode);
		$params = $notification->getSubjectParameters();

		$notification->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			)
		);

		switch ($notification->getSubject()) {
			case 'signature_completed':
				$signerName = $params['signerName'] ?? $params['signerEmail'] ?? 'Unknown';
				$fileName = $params['fileName'] ?? 'document';
				$notification->setParsedSubject(
					$l->t('%s ha firmato il documento "%s"', [$signerName, $fileName])
				);
				$notification->setRichSubject(
					$l->t('{signer} ha firmato il documento {file}'),
					[
						'signer' => [
							'type' => 'highlight',
							'id' => $params['signerEmail'] ?? 'unknown',
							'name' => $signerName,
						],
						'file' => [
							'type' => 'highlight',
							'id' => (string)($params['requestId'] ?? '0'),
							'name' => $fileName,
						],
					]
				);
				break;

			case 'signature_declined':
				$signerName = $params['signerName'] ?? $params['signerEmail'] ?? 'Unknown';
				$fileName = $params['fileName'] ?? 'document';
				$reason = !empty($params['reason']) ? ' - Motivo: ' . $params['reason'] : '';
				$notification->setParsedSubject(
					$l->t('%s ha rifiutato di firmare "%s"%s', [$signerName, $fileName, $reason])
				);
				$notification->setRichSubject(
					$l->t('{signer} ha rifiutato di firmare {file}'),
					[
						'signer' => [
							'type' => 'highlight',
							'id' => $params['signerEmail'] ?? 'unknown',
							'name' => $signerName,
						],
						'file' => [
							'type' => 'highlight',
							'id' => (string)($params['requestId'] ?? '0'),
							'name' => $fileName,
						],
					]
				);
				break;

			case 'all_signatures_completed':
				$fileName = $params['fileName'] ?? 'document';
				$notification->setParsedSubject(
					$l->t('Tutte le firme sono state completate per "%s". Il documento firmato è stato salvato.', [$fileName])
				);
				$notification->setRichSubject(
					$l->t('Tutte le firme sono state completate per {file}. Il documento firmato è stato salvato.'),
					[
						'file' => [
							'type' => 'highlight',
							'id' => (string)($params['requestId'] ?? '0'),
							'name' => $fileName,
						],
					]
				);
				break;

			case 'signature_expired':
				$fileName = $params['fileName'] ?? 'document';
				$notification->setParsedSubject(
					$l->t('La richiesta di firma per "%s" è scaduta', [$fileName])
				);
				$notification->setRichSubject(
					$l->t('La richiesta di firma per {file} è scaduta'),
					[
						'file' => [
							'type' => 'highlight',
							'id' => (string)($params['requestId'] ?? '0'),
							'name' => $fileName,
						],
					]
				);
				break;

			default:
				throw new UnknownNotificationException();
		}

		return $notification;
	}
}
