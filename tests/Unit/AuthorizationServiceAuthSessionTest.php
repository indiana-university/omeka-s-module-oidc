<?php

declare(strict_types=1);

namespace OIDC\Test\Unit;

use Facile\JoseVerifier\IdTokenVerifierInterface;
use Facile\OpenIDClient\AuthMethod\AuthMethodFactoryInterface;
use Facile\OpenIDClient\AuthMethod\AuthMethodInterface;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadataInterface;
use Facile\OpenIDClient\Issuer\IssuerInterface;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadataInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Token\IdTokenVerifierBuilderInterface;
use Facile\OpenIDClient\Token\TokenSet;
use Facile\OpenIDClient\Token\TokenSetFactoryInterface;
use Facile\OpenIDClient\Token\TokenVerifierBuilderInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class AuthorizationServiceAuthSessionTest extends TestCase
{
    public function testAuthSessionNonceIsRequiredDuringIdTokenVerification(): void
    {
        $authSession = new AuthSession();
        $authSession->setState('expected-state');
        $authSession->setNonce('expected-nonce');

        $idTokenVerifier = $this->createMock(IdTokenVerifierInterface::class);
        $idTokenVerifier->expects(self::once())
            ->method('withState')
            ->with('expected-state')
            ->willReturnSelf();
        $idTokenVerifier->expects(self::once())
            ->method('withCode')
            ->with(null)
            ->willReturnSelf();
        $idTokenVerifier->expects(self::once())
            ->method('withAccessToken')
            ->with(null)
            ->willReturnSelf();
        $idTokenVerifier->expects(self::once())
            ->method('withMaxAge')
            ->with(null)
            ->willReturnSelf();
        $idTokenVerifier->expects(self::once())
            ->method('withNonce')
            ->with('expected-nonce')
            ->willReturnSelf();
        $idTokenVerifier->expects(self::once())
            ->method('verify')
            ->with('id-token')
            ->willThrowException(new RuntimeException('nonce mismatch'));

        $idTokenVerifierBuilder = $this->createMock(IdTokenVerifierBuilderInterface::class);
        $idTokenVerifierBuilder->method('build')->willReturn($idTokenVerifier);
        $service = $this->createAuthorizationService($idTokenVerifierBuilder);

        $this->expectException(RuntimeException::class);
        $service->callback(
            $this->createMock(ClientInterface::class),
            ['id_token' => 'id-token'],
            null,
            $authSession
        );
    }

    public function testAuthSessionPkceVerifierIsSentDuringCodeExchange(): void
    {
        $authSession = new AuthSession();
        $authSession->setCodeVerifier(str_repeat('v', 43));

        $clientMetadata = $this->createMock(ClientMetadataInterface::class);
        $clientMetadata->method('getRedirectUris')->willReturn(['https://omeka.example.test/oidc/redirect']);
        $clientMetadata->method('getTokenEndpointAuthMethod')->willReturn('client_secret_basic');
        $clientMetadata->method('get')->with('token_endpoint_auth_method')->willReturn('client_secret_basic');

        $issuerMetadata = $this->createMock(IssuerMetadataInterface::class);
        $issuerMetadata->method('get')->with('token_endpoint')->willReturn('https://idp.example.test/token');
        $issuer = $this->createMock(IssuerInterface::class);
        $issuer->method('getMetadata')->willReturn($issuerMetadata);

        $authMethod = $this->createMock(AuthMethodInterface::class);
        $authMethod->expects(self::once())
            ->method('createRequest')
            ->with(
                self::isInstanceOf(RequestInterface::class),
                self::isInstanceOf(ClientInterface::class),
                self::callback(static function (array $claims): bool {
                    return 'authorization_code' === $claims['grant_type']
                        && 'auth-code' === $claims['code']
                        && str_repeat('v', 43) === $claims['code_verifier'];
                })
            )
            ->willReturnArgument(0);
        $authMethodFactory = $this->createMock(AuthMethodFactoryInterface::class);
        $authMethodFactory->method('create')->with('client_secret_basic')->willReturn($authMethod);

        $client = $this->createMock(ClientInterface::class);
        $client->method('getMetadata')->willReturn($clientMetadata);
        $client->method('getIssuer')->willReturn($issuer);
        $client->method('getAuthMethodFactory')->willReturn($authMethodFactory);
        $client->method('getHttpClient')->willReturn(null);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(new Response(
            200,
            ['content-type' => 'application/json'],
            '{"access_token":"access-token"}'
        ));
        $tokenSetFactory = $this->createMock(TokenSetFactoryInterface::class);
        $tokenSetFactory->method('fromArray')->willReturnCallback(
            static fn(array $params) => TokenSet::fromParams($params)
        );
        $service = new AuthorizationService(
            $tokenSetFactory,
            $httpClient,
            new HttpFactory(),
            $this->createMock(IdTokenVerifierBuilderInterface::class),
            $this->createMock(TokenVerifierBuilderInterface::class)
        );

        $tokenSet = $service->callback($client, ['code' => 'auth-code'], null, $authSession);

        self::assertSame('access-token', $tokenSet->getAccessToken());
    }

    private function createAuthorizationService(
        IdTokenVerifierBuilderInterface $idTokenVerifierBuilder
    ): AuthorizationService {
        $tokenSetFactory = $this->createMock(TokenSetFactoryInterface::class);
        $tokenSetFactory->method('fromArray')->willReturnCallback(
            static fn(array $params) => TokenSet::fromParams($params)
        );

        return new AuthorizationService(
            $tokenSetFactory,
            $this->createMock(HttpClientInterface::class),
            new HttpFactory(),
            $idTokenVerifierBuilder,
            $this->createMock(TokenVerifierBuilderInterface::class)
        );
    }
}
