<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Search;

use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class DocuSealSearchProvider implements IProvider {

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private SignatureRequestMapper $requestMapper,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10n->t('Firme DocuSeal');
	}

	public function getOrder(string $route, array $routeParameters): int {
		return 90;
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$term = strtolower($query->getTerm());
		$requests = $this->requestMapper->findByUserId(
			$user->getUID(),
			$query->getLimit(),
			$query->getCursor() ? (int)$query->getCursor() : 0,
		);

		$results = [];
		foreach ($requests as $request) {
			$fileName = $request->getFileName();
			if ($term !== '' && stripos($fileName, $term) === false
				&& stripos($request->getStatus(), $term) === false) {
				continue;
			}

			$statusLabels = [
				'pending' => $this->l10n->t('In attesa'),
				'sent' => $this->l10n->t('Inviato'),
				'completed' => $this->l10n->t('Completato'),
				'declined' => $this->l10n->t('Rifiutato'),
				'expired' => $this->l10n->t('Scaduto'),
				'cancelled' => $this->l10n->t('Annullato'),
			];
			$status = $statusLabels[$request->getStatus()] ?? $request->getStatus();

			$results[] = new SearchResultEntry(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
				),
				$fileName,
				$status . ' - ' . date('d/m/Y H:i', $request->getCreatedAt()),
				$request->getFileId() > 0
					? $this->urlGenerator->getAbsoluteURL('/f/' . $request->getFileId())
					: '',
				'icon-docuseal',
				false
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$results,
		);
	}
}
