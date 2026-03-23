<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Settings;

use OCA\DocuSeal\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$serverUrl = $this->appConfig->getValueString(Application::APP_ID, 'server_url', '');
		$apiKeySet = $this->appConfig->getValueString(Application::APP_ID, 'api_key', '') !== '';

		$webhookSecretSet = $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '') !== '';

		$this->initialState->provideInitialState('docuseal-config', [
			'server_url' => $serverUrl,
			'api_key_set' => $apiKeySet,
			'webhook_secret_set' => $webhookSecretSet,
		]);

		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
