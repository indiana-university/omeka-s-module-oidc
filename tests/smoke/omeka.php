<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Log\Logger;
use Laminas\View\Helper\BasePath;
use OIDC\Controller\OIDCController;
use OIDC\Form\OIDCForm;
use OIDC\Service\Controller\OIDCControllerFactory;

$modulePath = dirname(__DIR__, 2);
$omekaPath = getenv('OMEKA_PATH');
if (!$omekaPath || !is_file($omekaPath . '/bootstrap.php')) {
    fwrite(STDERR, "OMEKA_PATH must identify an Omeka S installation.\n");
    exit(1);
}

require $omekaPath . '/bootstrap.php';
require $modulePath . '/vendor/autoload.php';
require $modulePath . '/Module.php';

$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTP_HOST'] = 'omeka.example.test';

$basePath = new BasePath();
$basePath->setBasePath('');
$services = new class([
    'Omeka\EntityManager' => (new ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor(),
    'Omeka\AuthenticationService' => new AuthenticationService(),
    'ViewHelperManager' => new class($basePath) {
        public function __construct(private BasePath $basePath)
        {
        }

        public function get(string $name): BasePath
        {
            if ($name !== 'BasePath') {
                throw new RuntimeException('Unexpected view helper: ' . $name);
            }
            return $this->basePath;
        }
    },
    'Config' => ['oidc' => ['client_id' => 'fixture-client', 'client_secret' => 'fixture-secret']],
    'Omeka\Logger' => new Logger(),
]) implements ContainerInterface {
    public function __construct(private array $services)
    {
    }

    public function get($id)
    {
        if (!$this->has($id)) {
            throw new RuntimeException('Unexpected service: ' . $id);
        }
        return $this->services[$id];
    }

    public function has($id): bool
    {
        return array_key_exists($id, $this->services);
    }
};

$module = new OIDC\Module();
$config = $module->getConfig();
$factory = new OIDCControllerFactory();
$controller = $factory($services, OIDCController::class);

if ($config['controllers']['factories'][OIDCController::class] !== OIDCControllerFactory::class) {
    throw new RuntimeException('The Omeka controller factory configuration is invalid.');
}
if (!$controller instanceof OIDCController) {
    throw new RuntimeException('The Omeka service factory did not construct the OIDC controller.');
}

$insecureIssuerForm = new OIDCForm();
$insecureIssuerForm->init();
$insecureIssuerForm->setData(['oidc_discovery' => 'http://idp.example.test']);
if ($insecureIssuerForm->isValid()) {
    throw new RuntimeException('The module configuration accepted an insecure issuer URI.');
}

$secureIssuerForm = new OIDCForm();
$secureIssuerForm->init();
$secureIssuerForm->setData(['oidc_discovery' => 'https://idp.example.test']);
if (! $secureIssuerForm->isValid()) {
    throw new RuntimeException('The module configuration rejected a valid HTTPS issuer URI.');
}

fwrite(STDOUT, "OIDC module loaded and its controller was constructed in Omeka S.\n");
