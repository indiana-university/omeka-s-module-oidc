<?php

declare(strict_types=1);

namespace OIDC\Security;

final class UserInfoClaims
{
    public static function email(array $userInfo): ?string
    {
        $email = $userInfo['email'] ?? null;
        if (! is_string($email) || '' === trim($email)) {
            return null;
        }

        return trim($email);
    }
}
