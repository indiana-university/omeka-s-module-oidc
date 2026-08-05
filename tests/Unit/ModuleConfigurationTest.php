<?php

declare(strict_types=1);

namespace OIDC\Test\Unit;

use OIDC\Controller\OIDCController;
use OIDC\Service\Controller\OIDCControllerFactory;
use PHPUnit\Framework\TestCase;

final class ModuleConfigurationTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = require dirname(__DIR__, 2) . '/config/module.config.php';
    }

    public function testRegistersLiteralLoginAndRedirectRoutes(): void
    {
        $route = $this->config['router']['routes']['oidc'];

        self::assertSame('/oidc', $route['options']['route']);
        self::assertSame('/login', $route['child_routes']['login']['options']['route']);
        self::assertSame('/redirect', $route['child_routes']['redirect']['options']['route']);
        self::assertSame(
            OIDCController::class,
            $route['child_routes']['login']['options']['defaults']['controller']
        );
        self::assertSame(
            OIDCController::class,
            $route['child_routes']['redirect']['options']['defaults']['controller']
        );
    }

    public function testRegistersTheControllerFactory(): void
    {
        self::assertSame(
            OIDCControllerFactory::class,
            $this->config['controllers']['factories'][OIDCController::class]
        );
    }

    public function testDeclaresSupportForOmekaFour(): void
    {
        $metadata = parse_ini_file(
            dirname(__DIR__, 2) . '/config/module.ini',
            true,
            INI_SCANNER_TYPED
        );

        self::assertIsArray($metadata);
        self::assertSame('^4.0.0', $metadata['info']['omeka_version_constraint']);
        self::assertTrue($metadata['info']['configurable']);
    }
}
