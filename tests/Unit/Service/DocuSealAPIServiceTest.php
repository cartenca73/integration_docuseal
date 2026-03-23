<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Tests\Unit\Service;

use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DocuSealAPIServiceTest extends TestCase {

	private DocuSealAPIService $service;
	private IClientService $clientService;
	private IAppConfig $appConfig;
	private ICrypto $crypto;
	private LoggerInterface $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->clientService = $this->createMock(IClientService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new DocuSealAPIService(
			$this->clientService,
			$this->appConfig,
			$this->crypto,
			$this->logger,
		);
	}

	public function testIsConfiguredReturnsFalseWhenNoConfig(): void {
		$this->appConfig->method('getValueString')
			->willReturn('');

		$this->assertFalse($this->service->isConfigured());
	}

	public function testIsConfiguredReturnsTrueWhenConfigured(): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				return match ($key) {
					'server_url' => 'https://docuseal.example.com',
					'api_key' => 'encrypted_key',
					default => $default,
				};
			});

		$this->crypto->method('decrypt')
			->with('encrypted_key')
			->willReturn('actual_api_key');

		$this->assertTrue($this->service->isConfigured());
	}

	public function testGetServerUrl(): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				if ($key === 'server_url') {
					return 'https://docuseal.example.com/';
				}
				return $default;
			});

		// Should trim trailing slash
		$this->assertEquals('https://docuseal.example.com', $this->service->getServerUrl());
	}

	public function testTestConnectionSuccess(): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				return match ($key) {
					'server_url' => 'https://docuseal.example.com',
					'api_key' => 'encrypted_key',
					default => $default,
				};
			});

		$this->crypto->method('decrypt')
			->willReturn('actual_api_key');

		$response = $this->createMock(IResponse::class);
		$response->method('getBody')->willReturn('{"data":[]}');

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($response);

		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->testConnection();
		$this->assertTrue($result['success']);
	}

	public function testTestConnectionFailure(): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $app, string $key, string $default) {
				return match ($key) {
					'server_url' => 'https://docuseal.example.com',
					'api_key' => 'encrypted_key',
					default => $default,
				};
			});

		$this->crypto->method('decrypt')
			->willReturn('actual_api_key');

		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(new \Exception('Connection refused'));

		$this->clientService->method('newClient')->willReturn($client);

		$result = $this->service->testConnection();
		$this->assertFalse($result['success']);
		$this->assertStringContainsString('Connection refused', $result['message']);
	}
}
