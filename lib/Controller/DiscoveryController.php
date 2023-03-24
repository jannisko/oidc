<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022-2023 Thorsten Jagel <dev@jagel.net>
 *
 * @author Thorsten Jagel <dev@jagel.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\OIDCIdentityProvider\Controller;

use OC\Security\Bruteforce\Throttler;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class DiscoveryController extends ApiController
{
	/** @var ITimeFactory */
	private $time;
	/** @var Throttler */
	private $throttler;
    /** @var IURLGenerator */
	private $urlGenerator;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
					string $appName,
					IRequest $request,
					ITimeFactory $time,
					Throttler $throttler,
					IURLGenerator $urlGenerator,
					LoggerInterface $logger
					)
	{
		parent::__construct($appName, $request);
		$this->time = $time;
		$this->throttler = $throttler;
        $this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @return Response
	 */
	public function discoveryCors(): Response {
		$response = new Response();
		$response->addHeader('Access-Control-Allow-Origin', '*');
		$response->addHeader('Access-Control-Allow-Methods', 'PUT, POST, GET, DELETE, PATCH');
		$response->addHeader('Access-Control-Max-Age', '1728000');
		$response->addHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
		$response->addHeader('Access-Control-Allow-Credentials', 'false');
		return $response;
	}

	/**
     * @PublicPage
	 * @NoCSRFRequired
     *
     * Must be proviced at path:
     * <issuer>//.well-known/openid-configuration
	 *
	 * @return JSONResponse
	 */
	public function getInfo(): JSONResponse
	{
		$host = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();
        $issuer = $host . $this->urlGenerator->getWebroot();
        $scopesSupported = [
            'openid',
            'profile',
            'email',
            'roles',
            'groups',
        ];
        $responseTypesSupported = [
            'code',
            'code id_token',
            // 'code token',
            // 'code id_token token',
            'id_token',
            // 'id_token token'
        ];
        $responseModesSupported = [
            'query',
            // 'fragment',
        ];
        $grantTypesSupported = [
            'authorization_code',
            'implicit',
        ];
        $acrValuesSupported = [
            '0',
        ];
        $subjectTypesSupported = [
            // 'pairwise',
            'public',
        ];
        $idTokenSigningAlgValuesSupported = [
            'RS256',
            'HS256',
        ];
        $userinfoSigningAlgValuesSupported = [
            'none',
        ];
        $tokenEndpointAuthMethodsSupported = [
            'client_secret_post',
            // 'client_secret_basic',
            // 'client_secret_jwt',
            // 'private_key_jwt',
        ];
        $displayValuesSupported = [
            'page',
            // 'popup',
            // 'touch',
            // 'wap',
        ];
        $claimTypesSupported = [
            'normal',
            // 'aggregated',
            // 'distributed',
        ];
        $claimsSupported = [
            'iss',
			'sub',
			'aud',
			'exp',
			'auth_time',
			'iat',
			'acr',
			'azp',
			'preferred_username',
			'scope',
			'nbf',
			'jti',
            'roles',
            'name',
            'updated_at',
            'website',
            'email',
            'email_verified',
        ];

		$discoveryPayload = [
			'issuer' => $issuer,
			'authorization_endpoint' => $host . $this->urlGenerator->linkToRoute('oidc.LoginRedirector.authorize', []),
			'token_endpoint' => $host . $this->urlGenerator->linkToRoute('oidc.OIDCApi.getToken', []),
            'userinfo_endpoint' => $host . $this->urlGenerator->linkToRoute('oidc.UserInfo.getInfo', []),
            'jwks_uri' => $host . $this->urlGenerator->linkToRoute('oidc.Jwks.getKeyInfo', []),
            'scopes_supported' => $scopesSupported,
            'response_types_supported' => $responseTypesSupported,
            'response_modes_supported' => $responseModesSupported,
            'grant_types_supported' => $grantTypesSupported,
            'acr_values_supported' => $acrValuesSupported,
            'subject_types_supported' => $subjectTypesSupported,
            'id_token_signing_alg_values_supported' => $idTokenSigningAlgValuesSupported,
            // 'id_token_encryption_alg_values_supported' => ,
            // 'id_token_encryption_enc_values_supported' => ,
            'userinfo_signing_alg_values_supported' => $userinfoSigningAlgValuesSupported,
            // 'userinfo_encryption_alg_values_supported' => ,
            // 'userinfo_encryption_enc_values_supported' => ,
            // 'request_object_signing_alg_values_supported' => ,
            // 'request_object_encryption_alg_values_supported' => ,
            // 'request_object_encryption_enc_values_supported' => ,
            'token_endpoint_auth_methods_supported' => $tokenEndpointAuthMethodsSupported,
            // 'token_endpoint_auth_signing_alg_values_supported' => ,
            'display_values_supported' => $displayValuesSupported,
            'claim_types_supported' => $claimTypesSupported,
            'claims_supported' => $claimsSupported,
            // 'service_documentation' => ,
            // 'claims_locales_supported' => ,
            // 'ui_locales_supported' => ,
            // 'claims_parameter_supported' => true,
            // 'request_parameter_supported' => true,
            // 'request_uri_parameter_supported' => false,
            // 'require_request_uri_registration' => true,
            // 'op_policy_uri' => ,
            // 'op_tos_uri' => ,
			'end_session_endpoint' => $host . $this->urlGenerator->linkToRoute('oidc.Logout.logout', []),
		];

		$this->logger->info('Request to Discovery Endpoint.');

		return new JSONResponse($discoveryPayload);
	}

}
