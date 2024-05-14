<?php

namespace OCA\OIDCIdentityProvider\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

use OCP\AppFramework\Http;
use OCP\IRequest;
use OC\Security\Bruteforce\Throttler;
use OC\Security\Bruteforce\Backend\IBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCA\OIDCIdentityProvider\Db\ClientMapper;
use OCA\OIDCIdentityProvider\Db\AccessTokenMapper;
use OCA\OIDCIdentityProvider\Db\RedirectUriMapper;
use OCA\OIDCIdentityProvider\Db\LogoutRedirectUriMapper;
use OCP\Security\ISecureRandom;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Services\IAppConfig;
use OCP\ILogger;

use OCA\OIDCIdentityProvider\Controller\DynamicRegistrationController;

class DynamicRegistrationControllerTest extends TestCase {
    protected $controller;
    /** @var IRequest */
    protected $request;
    /** @var ClientMapper */
    protected $clientMapper;
    /** @var ISecureRandom */
    protected $secureRandom;
    /** @var AccessTokenMapper  */
    protected $accessTokenMapper;
    /** @var RedirectUriMapper  */
    protected $redirectUriMapper;
    /** @var LogoutRedirectUriMapper  */
    protected $logoutRedirectUriMapper;
    /** @var ITimeFactory */
    protected $time;
	/** @var IBackend */
	protected $throttlerBackend;
    /** @var Throttler */
    protected $throttler;
    /** @var IURLGenerator */
    protected $urlGenerator;
	/** @var IConfig */
	protected $config;
    /** @var IAppConfig */
    protected $appConfig;
    /** @var IDBConnection */
    protected $db;
    /** @var LoggerInterface */
    protected $logger;

    public function setUp(): void {
		parent::setUp();
        $this->request = $this->getMockBuilder(IRequest::class)->getMock();
        $this->db = $this->getMockBuilder(IDBConnection::class)->getMock();
        $this->secureRandom = $this->getMockBuilder(ISecureRandom::class)->getMock();
        $this->time = $this->getMockBuilder(ITimeFactory::class)->getMock();
        $this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $this->throttlerBackend = $this->getMockBuilder(IBackend::class)->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->appConfig = $this->getMockBuilder(IAppConfig::class)->getMock();
        $this->accessTokenMapper = $this->getMockBuilder(AccessTokenMapper::class)->setConstructorArgs([$this->db,
                                                                                                        $this->time,
                                                                                                        $this->appConfig])->getMock();
        $this->redirectUriMapper = $this->getMockBuilder(RedirectUriMapper::class)->setConstructorArgs([$this->db,
                                                                                                        $this->time,
                                                                                                        $this->appConfig])->getMock();

        $this->logoutRedirectUriMapper = $this->getMockBuilder(LogoutRedirectUriMapper::class)->setConstructorArgs([$this->db,
                                                                                                                    $this->time,
                                                                                                                    $this->appConfig])->getMock();

        $this->throttler = $this->getMockBuilder(Throttler::class)->setConstructorArgs([$this->time,
                                                                                        $this->logger,
                                                                                        $this->config,
                                                                                        $this->throttlerBackend])->getMock();
        $this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->clientMapper = $this->getMockBuilder(ClientMapper::class)->setConstructorArgs([$this->db,
                                                                                              $this->time,
                                                                                              $this->appConfig,
                                                                                              $this->redirectUriMapper])->getMock();


        $this->controller = new DynamicRegistrationController(
            'oidc',
            $this->request,
            $this->clientMapper,
            $this->secureRandom,
            $this->accessTokenMapper,
            $this->redirectUriMapper,
            $this->logoutRedirectUriMapper,
            $this->time,
            $this->throttler,
            $this->urlGenerator,
            $this->appConfig,
            $this->logger
        );
    }

    public function testDisabled() {
        $result = $this->controller->registerClient();

        $this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
        $this->assertEquals('dynamic_registration_not_allowed', $result->getData()['error']);
    }

	public function testNoRedirectUris() {
		// Return true for getAppValue('dynamic_client_registration', 'false')
		$this->appConfig
			->method('getAppValue')
			->willReturn('true');

        $result = $this->controller->registerClient();

        $this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
        $this->assertEquals('no_redirect_uris_provided', $result->getData()['error']);
    }

	public function testEmptyRedirectUris() {
		// Return true for getAppValue('dynamic_client_registration', 'false')
		$this->appConfig
			->method('getAppValue')
			->willReturn('true');

        $result = $this->controller->registerClient([]);

        $this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
        $this->assertEquals('no_redirect_uris_provided', $result->getData()['error']);
    }

	public function testMaxNumClientsExceeded() {
		// Return true for getAppValue('dynamic_client_registration', 'false')
		$this->appConfig
			->method('getAppValue')
			->willReturn('true');

		// Return max number of clients 1000
		$this->clientMapper
			->method('getNumDcrClients')
			->willReturn(1000);

        $result = $this->controller->registerClient(['https://test.org/redirect']);

        $this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
        $this->assertEquals('max_num_clients_exceeded', $result->getData()['error']);
    }

	public function testClientCreated() {
		// Return true for getAppValue('dynamic_client_registration', 'false')
		$this->appConfig
			->method('getAppValue')
			->willReturnMap([
				['dynamic_client_registration', 'false', 'true'],
				['client_expire_time', '3600', '3600']
			]);

		// Return max number of clients 1000
		$this->clientMapper
			->method('getNumDcrClients')
			->willReturn(2);

		$this->clientMapper
			->method('insert')
			->willReturnCallBack (
				function ($arg) {
					return $arg;
				}
			);

        $result = $this->controller->registerClient(['https://test.org/redirect'], 'TEST-CLIENT');

        $this->assertEquals(Http::STATUS_CREATED, $result->getStatus());
		var_dump($result->getData());
        $this->assertEquals('TEST-CLIENT', $result->getData()['client_name']);
    }

}
