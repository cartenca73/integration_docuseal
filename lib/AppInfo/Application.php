<?php

declare(strict_types=1);

namespace OCA\DocuSeal\AppInfo;

use OCA\DocuSeal\Activity\Provider as ActivityProvider;
use OCA\DocuSeal\Activity\Setting as ActivitySetting;
use OCA\DocuSeal\Dashboard\DocuSealWidget;
use OCA\DocuSeal\Listener\CSPListener;
use OCA\DocuSeal\Notification\Notifier;
use OCA\DocuSeal\Search\DocuSealSearchProvider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_docuseal';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Notifications
		$context->registerNotifierService(Notifier::class);

		// Unified Search
		$context->registerSearchProvider(DocuSealSearchProvider::class);

		// Dashboard Widget
		$context->registerDashboardWidget(DocuSealWidget::class);

		// Activity
		$context->registerEventListener(
			\OCP\Activity\IManager::class,
			ActivityProvider::class
		);

		// CSP for iframe embedding
		$context->registerEventListener(
			AddContentSecurityPolicyEvent::class,
			CSPListener::class
		);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IEventDispatcher $eventDispatcher): void {
			$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function (): void {
				Util::addScript(self::APP_ID, self::APP_ID . '-filesplugin');
				Util::addScript(self::APP_ID, self::APP_ID . '-sidebar');
				Util::addStyle(self::APP_ID, 'files-style');
			});
		});
	}
}
