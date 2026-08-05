<?php

declare(strict_types=1);

namespace OIDC\Test\Unit;

use OIDC\Security\ProviderMetadataValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ProviderMetadataValidatorTest extends TestCase
{
    private ProviderMetadataValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProviderMetadataValidator();
    }

    public function testAcceptsValidHttpsProviderMetadata(): void
    {
        $metadata = $this->validMetadata();
        $metadata['authorization_endpoint'] = 'https://idp.example.test:443/authorize?audience=omeka';

        $this->validator->validateMetadata('https://idp.example.test', $metadata);

        self::addToAssertionCount(1);
    }

    #[DataProvider('invalidIssuers')]
    public function testRejectsInvalidConfiguredIssuer(string $issuer): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validateIssuer($issuer);
    }

    public function testRejectsIssuerMismatch(): void
    {
        $metadata = $this->validMetadata();
        $metadata['issuer'] = 'https://idp.example.test/different';

        $this->expectException(UnexpectedValueException::class);
        $this->validator->validateMetadata('https://idp.example.test', $metadata);
    }

    #[DataProvider('requiredEndpoints')]
    public function testRejectsMissingSecuritySensitiveEndpoint(string $endpoint): void
    {
        $metadata = $this->validMetadata();
        unset($metadata[$endpoint]);

        $this->expectException(UnexpectedValueException::class);
        $this->validator->validateMetadata('https://idp.example.test', $metadata);
    }

    #[DataProvider('invalidEndpoints')]
    public function testRejectsInvalidSecuritySensitiveEndpoint(string $endpoint, mixed $value): void
    {
        $metadata = $this->validMetadata();
        $metadata[$endpoint] = $value;

        $this->expectException(UnexpectedValueException::class);
        $this->validator->validateMetadata('https://idp.example.test', $metadata);
    }

    public static function invalidIssuers(): array
    {
        return [
            ['http://idp.example.test'],
            ['ftp://idp.example.test'],
            ['//idp.example.test'],
            ['https://user@idp.example.test'],
            ['https://idp.example.test?tenant=one'],
            ['https://idp.example.test#fragment'],
            ['https://bad host.example.test'],
            ['https:///missing-host'],
        ];
    }

    public static function requiredEndpoints(): array
    {
        return [
            ['authorization_endpoint'],
            ['token_endpoint'],
            ['jwks_uri'],
            ['userinfo_endpoint'],
        ];
    }

    public static function invalidEndpoints(): array
    {
        return [
            ['authorization_endpoint', 'http://idp.example.test/authorize'],
            ['token_endpoint', 'https://attacker.example.test/token'],
            ['jwks_uri', 'https://user@idp.example.test/jwks'],
            ['userinfo_endpoint', 'https://idp.example.test/userinfo#fragment'],
            ['authorization_endpoint', 'https://bad host.example.test/authorize'],
            ['token_endpoint', 'https://idp.example.test:8443/token'],
            ['jwks_uri', null],
            ['userinfo_endpoint', ''],
        ];
    }

    private function validMetadata(): array
    {
        return [
            'issuer' => 'https://idp.example.test',
            'authorization_endpoint' => 'https://idp.example.test/authorize',
            'token_endpoint' => 'https://idp.example.test/token',
            'jwks_uri' => 'https://idp.example.test/jwks',
            'userinfo_endpoint' => 'https://idp.example.test/userinfo',
        ];
    }
}
