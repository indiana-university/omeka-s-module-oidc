<?php

declare(strict_types=1);

namespace OIDC\Security;

use UnexpectedValueException;

final class ProviderMetadataValidator
{
    private const ENDPOINTS = [
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
        'userinfo_endpoint',
    ];

    public function validateIssuer(string $issuer): void
    {
        $this->validateHttpsUri($issuer, false);
    }

    public function validateMetadata(string $configuredIssuer, array $metadata): void
    {
        $this->validateIssuer($configuredIssuer);

        $discoveredIssuer = $metadata['issuer'] ?? null;
        if (! is_string($discoveredIssuer) || $configuredIssuer !== $discoveredIssuer) {
            throw new UnexpectedValueException('OIDC issuer metadata does not match configuration.');
        }

        $issuerParts = $this->validateHttpsUri($discoveredIssuer, false);
        $issuerOrigin = $this->origin($issuerParts);

        foreach (self::ENDPOINTS as $name) {
            $endpoint = $metadata[$name] ?? null;
            if (! is_string($endpoint)) {
                throw new UnexpectedValueException('OIDC metadata is missing a required endpoint.');
            }

            $endpointParts = $this->validateHttpsUri($endpoint, true);
            if ($issuerOrigin !== $this->origin($endpointParts)) {
                throw new UnexpectedValueException('OIDC endpoint origin does not match the issuer.');
            }
        }
    }

    private function validateHttpsUri(string $uri, bool $allowQuery): array
    {
        if (false === filter_var($uri, FILTER_VALIDATE_URL)) {
            throw new UnexpectedValueException('OIDC URI is malformed.');
        }

        $parts = parse_url($uri);
        if (! is_array($parts)
            || 'https' !== strtolower($parts['scheme'] ?? '')
            || ! isset($parts['host'])
            || '' === $parts['host']
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (! $allowQuery && isset($parts['query']))
            || ! $this->isValidHost($parts['host'])
        ) {
            throw new UnexpectedValueException('OIDC URI is not a trusted HTTPS URI.');
        }

        return $parts;
    }

    private function isValidHost(string $host): bool
    {
        $ipAddress = trim($host, '[]');

        return false !== filter_var($ipAddress, FILTER_VALIDATE_IP)
            || false !== filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    private function origin(array $parts): string
    {
        return strtolower($parts['scheme'])
            . '://'
            . strtolower($parts['host'])
            . ':'
            . ($parts['port'] ?? 443);
    }
}
