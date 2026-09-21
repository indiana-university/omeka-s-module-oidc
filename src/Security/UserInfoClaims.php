<?php

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
