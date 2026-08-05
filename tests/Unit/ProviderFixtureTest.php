<?php

declare(strict_types=1);

namespace OIDC\Test\Unit;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderFixtureTest extends TestCase
{
    #[DataProvider('fixtureNames')]
    public function testProviderFixtureIsValidJson(string $name): void
    {
        $contents = file_get_contents(dirname(__DIR__) . '/fixtures/provider/' . $name);

        self::assertNotFalse($contents);
        self::assertIsArray(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testFixtureUsesOnlyReservedExampleEndpoints(): void
    {
        $discovery = $this->readFixture('openid-configuration.json');

        self::assertSame('https://idp.example.test', $discovery['issuer']);
        self::assertStringStartsWith('https://idp.example.test/', $discovery['authorization_endpoint']);
        self::assertStringStartsWith('https://idp.example.test/', $discovery['token_endpoint']);
        self::assertStringStartsWith('https://idp.example.test/', $discovery['jwks_uri']);
        self::assertContains('S256', $discovery['code_challenge_methods_supported']);
    }

    public function testUserFixtureContainsEligibilityClaims(): void
    {
        $userInfo = $this->readFixture('userinfo.json');

        self::assertSame('fixture-subject-123', $userInfo['sub']);
        self::assertSame('researcher@example.test', $userInfo['email']);
        self::assertTrue($userInfo['email_verified']);
        self::assertContains('omeka-users', $userInfo['groups']);
    }

    public function testJwksFixtureRepresentsKeyRotation(): void
    {
        $jwks = $this->readFixture('jwks.json');
        $keyIds = array_column($jwks['keys'], 'kid');

        self::assertCount(2, $keyIds);
        self::assertSame($keyIds, array_unique($keyIds));
    }

    public static function fixtureNames(): array
    {
        return [['openid-configuration.json'], ['jwks.json'], ['userinfo.json']];
    }

    private function readFixture(string $name): array
    {
        $contents = file_get_contents(dirname(__DIR__) . '/fixtures/provider/' . $name);
        self::assertNotFalse($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
