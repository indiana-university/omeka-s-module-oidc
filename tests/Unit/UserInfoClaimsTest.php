<?php

namespace OIDC\Test\Unit;

use OIDC\Security\UserInfoClaims;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserInfoClaimsTest extends TestCase
{
    #[DataProvider('unusableEmailProvider')]
    public function testReturnsNullForMissingOrUnusableEmail(array $userInfo): void
    {
        self::assertNull(UserInfoClaims::email($userInfo));
    }

    public static function unusableEmailProvider(): array
    {
        return [
            'missing' => [[]],
            'null' => [['email' => null]],
            'empty' => [['email' => '']],
            'whitespace' => [['email' => '   ']],
            'non-string' => [['email' => ['unexpected']]],
        ];
    }

    public function testReturnsTrimmedEmail(): void
    {
        self::assertSame(
            'researcher@example.test',
            UserInfoClaims::email(['email' => ' researcher@example.test '])
        );
    }
}
