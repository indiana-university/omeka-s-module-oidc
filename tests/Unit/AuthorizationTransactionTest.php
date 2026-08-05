<?php

declare(strict_types=1);

namespace OIDC\Test\Unit;

use OIDC\Session\AuthorizationTransaction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

final class AuthorizationTransactionTest extends TestCase
{
    public function testStartsTransactionWithStateNonceAndPkce(): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();

        $authSession = $transaction->start($storage, 1_000);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $authSession->getState());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $authSession->getNonce());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $authSession->getCodeVerifier());
        self::assertSame(1_000, $authSession->getCustoms()['created_at']);
        self::assertSame($authSession->jsonSerialize(), $storage->oidc_auth_session);
        self::assertSame(
            $this->base64UrlEncode(hash('sha256', $authSession->getCodeVerifier(), true)),
            $transaction->getCodeChallenge($authSession)
        );
    }

    public function testConsumesValidTransactionBeforeReturningIt(): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $started = $transaction->start($storage, 1_000);

        $consumed = $transaction->consume($storage, $started->getState(), 1_100);

        self::assertSame($started->jsonSerialize(), $consumed->jsonSerialize());
        self::assertFalse(property_exists($storage, 'oidc_auth_session'));
    }

    #[DataProvider('invalidStates')]
    public function testRejectsInvalidStateAndConsumesTransaction(mixed $state): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $transaction->start($storage, 1_000);

        try {
            $transaction->consume($storage, $state, 1_100);
            self::fail('Invalid state was accepted.');
        } catch (UnexpectedValueException) {
            self::assertFalse(property_exists($storage, 'oidc_auth_session'));
        }
    }

    public function testRejectsExpiredTransaction(): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $authSession = $transaction->start($storage, 1_000);

        $this->expectException(UnexpectedValueException::class);
        $transaction->consume(
            $storage,
            $authSession->getState(),
            1_000 + AuthorizationTransaction::MAX_AGE_SECONDS + 1
        );
    }

    public function testRejectsReplayedTransaction(): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $authSession = $transaction->start($storage, 1_000);
        $transaction->consume($storage, $authSession->getState(), 1_100);

        $this->expectException(UnexpectedValueException::class);
        $transaction->consume($storage, $authSession->getState(), 1_100);
    }

    public function testRejectsInvalidPkceVerifier(): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $authSession = $transaction->start($storage, 1_000);
        $storage->oidc_auth_session['code_verifier'] = 'invalid';

        $this->expectException(UnexpectedValueException::class);
        $transaction->consume($storage, $authSession->getState(), 1_100);
    }

    #[DataProvider('requiredSessionValues')]
    public function testRejectsTransactionMissingRequiredSessionValue(string $key): void
    {
        $storage = new stdClass();
        $transaction = new AuthorizationTransaction();
        $authSession = $transaction->start($storage, 1_000);
        unset($storage->oidc_auth_session[$key]);

        $this->expectException(UnexpectedValueException::class);
        $transaction->consume($storage, $authSession->getState(), 1_100);
    }

    public static function invalidStates(): array
    {
        return [[null], [''], ['wrong-state'], [123]];
    }

    public static function requiredSessionValues(): array
    {
        return [['nonce'], ['code_verifier']];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
