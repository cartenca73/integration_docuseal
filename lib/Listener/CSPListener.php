<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Listener;

use OCA\DocuSeal\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		$serverUrl = $this->appConfig->getValueString(Application::APP_ID, 'server_url', '');
		if ($serverUrl === '') {
			return;
		}

		// Allow the DocuSeal server URL as frame-src for embedded signing
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain($serverUrl);
		$csp->addAllowedConnectDomain($serverUrl);

		$event->addPolicy($csp);
	}
}
