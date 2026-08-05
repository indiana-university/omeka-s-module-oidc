<?php

declare(strict_types=1);

namespace OIDC\Session;

use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Session\AuthSessionInterface;
use UnexpectedValueException;

use function Facile\OpenIDClient\base64url_encode;

final class AuthorizationTransaction
{
    public const MAX_AGE_SECONDS = 600;

    private const SESSION_KEY = 'oidc_auth_session';

    public function start(object $storage, ?int $now = null): AuthSessionInterface
    {
        $authSession = new AuthSession();
        $authSession->setState(base64url_encode(random_bytes(32)));
        $authSession->setNonce(base64url_encode(random_bytes(32)));
        $authSession->setCodeVerifier(base64url_encode(random_bytes(64)));
        $authSession->setCustoms(['created_at' => $now ?? time()]);

        $storage->{self::SESSION_KEY} = $authSession->jsonSerialize();

        return $authSession;
    }

    public function consume(object $storage, mixed $callbackState, ?int $now = null): AuthSessionInterface
    {
        $stored = $storage->{self::SESSION_KEY} ?? null;
        unset($storage->{self::SESSION_KEY});

        if (! is_array($stored)) {
            throw new UnexpectedValueException('OIDC authorization transaction is missing.');
        }

        $expectedState = $stored['state'] ?? null;
        $nonce = $stored['nonce'] ?? null;
        $codeVerifier = $stored['code_verifier'] ?? null;
        $createdAt = $stored['customs']['created_at'] ?? null;
        $now ??= time();

        if (! is_string($expectedState) || '' === $expectedState
            || ! is_string($nonce) || '' === $nonce
            || ! is_string($codeVerifier)
            || 1 !== preg_match('/\A[A-Za-z0-9_-]{43,128}\z/', $codeVerifier)
            || ! is_int($createdAt)
            || $createdAt > $now
            || ($now - $createdAt) > self::MAX_AGE_SECONDS
        ) {
            throw new UnexpectedValueException('OIDC authorization transaction is invalid or expired.');
        }

        if (! is_string($callbackState) || '' === $callbackState
            || ! hash_equals($expectedState, $callbackState)
        ) {
            throw new UnexpectedValueException('OIDC state is missing or invalid.');
        }

        return AuthSession::fromArray($stored);
    }

    public function getCodeChallenge(AuthSessionInterface $authSession): string
    {
        $codeVerifier = $authSession->getCodeVerifier();
        if (null === $codeVerifier) {
            throw new UnexpectedValueException('OIDC PKCE verifier is missing.');
        }

        return base64url_encode(hash('sha256', $codeVerifier, true));
    }
}
