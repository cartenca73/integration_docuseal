<?php

declare(strict_types=1);

namespace OCA\DocuSeal\AppInfo;

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

		// Load file action scripts in constructor (same pattern as integration_docusign)
		$container = $this->getContainer();
		$eventDispatcher = $container->get(IEventDispatcher::class);
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function (): void {
			Util::addScript(self::APP_ID, self::APP_ID . '-filesplugin');
			Util::addStyle(self::APP_ID, 'files-style');
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerSearchProvider(DocuSealSearchProvider::class);
		$context->registerDashboardWidget(DocuSealWidget::class);
		$context->registerEventListener(
			AddContentSecurityPolicyEvent::class,
			CSPListener::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
