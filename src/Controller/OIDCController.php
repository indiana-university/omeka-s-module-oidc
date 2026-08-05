<?php

namespace OIDC\Controller;

use DateTime;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Authentication\AuthenticationService;
use Laminas\Session\Container;
use Laminas\View\Helper\BasePath;
use Laminas\Log\Logger;
use Omeka\Entity\User;
use Omeka\Entity\SitePermission;
use Omeka\Permissions\Acl;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Service\Builder\UserInfoServiceBuilder;
use Facile\OpenIDClient\Token\TokenSet;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ServerRequestFactory;
use OIDC\Security\ProviderMetadataValidator;
use OIDC\Session\AuthorizationTransaction;

class OIDCController extends AbstractActionController
{
    protected $entityManager;
    protected $auth;
    protected $logger;
    private $basePath;
    private $redirect;
    private $authorizationService;
    private $authorizationTransaction;
    private $providerMetadataValidator;
    private $config;

    public function __construct(EntityManager $entityManager, AuthenticationService $auth, BasePath $basePath, array $config, Logger $logger)
    {
        $this->entityManager = $entityManager;
        $this->auth = $auth;
        $this->basePath = $basePath;
        $this->redirect = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $basePath() . "/oidc/redirect";
        $this->authorizationService = (new AuthorizationServiceBuilder())->build();
        $this->authorizationTransaction = new AuthorizationTransaction();
        $this->providerMetadataValidator = new ProviderMetadataValidator();
        $this->config = $config;
        $this->logger = $logger;
    }

    public function loginAction()
    {
        if ($this->auth->hasIdentity()) {
            return $this->redirect()->toRoute('top');
        }

        try {
            $session = Container::getDefaultManager()->getStorage();
            $client = $this->getClient();
            $authorizationService = $this->authorizationService;
            $authSession = $this->authorizationTransaction->start($session);

            //Use this uri to redirect the user for authN
            $redirectAuthorizationUri = $authorizationService->getAuthorizationUri(
                $client,
                [
                    'login_hint' => 'username@example.com',
                    'scope' => 'openid email',
                    'nonce' => $authSession->getNonce(),
                    'state' => $authSession->getState(),
                    'code_challenge' => $this->authorizationTransaction->getCodeChallenge($authSession),
                    'code_challenge_method' => 'S256',

                ] // custom params
            );
            return $this->redirect()->toUrl($redirectAuthorizationUri);
        } catch (\Throwable) {
            $this->logger()->info('OIDC: Authentication failed');
            return $this->redirect()->toRoute('top');
        }
    }

    public function redirectAction()
    {
        $log = $this->logger();
        $sessionManager = Container::getDefaultManager();
        $session = $sessionManager->getStorage();
        $authorizationService = $this->authorizationService;
        $request = $this->request;

        try {
            $authSession = $this->authorizationTransaction->consume(
                $session,
                $request->getQuery('state')
            );
            $code = $request->getQuery('code');
            if (! is_string($code) || '' === $code) {
                throw new \UnexpectedValueException('OIDC authorization code is missing.');
            }

            $client = $this->getClient();
            $location = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $request->getUriString();
            $location = filter_var($location, FILTER_SANITIZE_URL);
            $serverRequestFactory = new ServerRequestFactory();
            $serverRequest = $serverRequestFactory->createServerRequest('GET', $location);

            $callbackParams = $authorizationService->getCallbackParams($serverRequest, $client);
            $expectedState = $authSession->getState();
            $callbackState = $callbackParams['state'] ?? null;
            if (! is_string($expectedState)
                || ! is_string($callbackState)
                || ! hash_equals($expectedState, $callbackState)
            ) {
                throw new \UnexpectedValueException('OIDC callback state is invalid.');
            }

            $tokenSet = $authorizationService->callback($client, $callbackParams, null, $authSession);

            // Get user info
            $userInfoService = (new UserInfoServiceBuilder())->build();
            $userInfo = $userInfoService->getUserInfo($client, $tokenSet);
            $email = $userInfo['email'];
            //TODO: need to add some error checking here
            $user = $this->getUser($email);
            if (!isset($user)) {
                throw new \UnexpectedValueException('OIDC user is invalid.');
            }

            $sessionManager->regenerateId();
            $session->expiration_timestamp = time() + $tokenSet->getExpiresIn();
            $this->auth->getStorage()->write($user);
            return $this->redirect()->toRoute('top');
        } catch (\Throwable) {
            $log->info('OIDC: Authentication failed');
            return $this->redirect()->toRoute('top');
        }
    }

    protected function getUser($oidc)
    {
            $em = $this->entityManager;
            $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['email' => $oidc]);

            //Create user if they do not already exist in Omeka
            if (!$user)
            {
                $user = new User();
                $user->setName($oidc);
                $user->setEmail($oidc);
                $user->setRole($this->settings()->get('oidc_role', Acl::ROLE_RESEARCHER));
                $user->setIsActive(true);
                $dt = new DateTime('now');
                $user->setCreated($dt);
                $user->setModified($dt);
                $em->persist($user);
                $em->flush();
        
                //TODO: Add user to "public" site(s) if the setting exists
            }
            return $user;
    }

    protected function getClient()
    {
        $redirect = $this->redirect;
        $configuredIssuer = $this->settings()->get('oidc_discovery');
        if (! is_string($configuredIssuer)) {
            throw new \UnexpectedValueException('OIDC issuer is not configured.');
        }

        $this->providerMetadataValidator->validateIssuer($configuredIssuer);
        $issuer = (new IssuerBuilder())->build($configuredIssuer);
        $this->providerMetadataValidator->validateMetadata(
            $configuredIssuer,
            $issuer->getMetadata()->toArray()
        );
	    $config = $this->config;

	    $clientId = $config['oidc']['client_id'];
	    $clientSecret = $config['oidc']['client_secret'];

        $clientMetadata = ClientMetadata::fromArray([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'token_endpoint_auth_method' => 'client_secret_basic', // the auth method for the token endpoint
            'redirect_uris' => [
                    $redirect,
            ],
        ]);

        $client = (new ClientBuilder())
            ->setIssuer($issuer)
            ->setClientMetadata($clientMetadata)
            ->build();
        return $client;
    }
}

