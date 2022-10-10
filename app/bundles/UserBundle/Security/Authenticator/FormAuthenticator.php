<?php

namespace Mautic\UserBundle\Security\Authenticator;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Event\AuthenticationEvent;
use Mautic\UserBundle\Security\Authentication\Token\PluginToken;
use Mautic\UserBundle\UserEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'login';

    private UserPasswordEncoder $encoder;

    private EventDispatcherInterface $dispatcher;

    private IntegrationHelper $integrationHelper;

    private ?RequestStack $requestStack;

    private CsrfTokenManagerInterface $csrfTokenManager;

    private UrlGeneratorInterface $urlGenerator;

    /**
     * @var string|null After upgrade to Symfony 5.2 we should use Passport system to store the authenticatingService
     */
    private ?string $authenticatingService = null;

    private ?Response $authEventResponse;

    public function __construct(
        IntegrationHelper $integrationHelper,
        UserPasswordEncoder $encoder,
        EventDispatcherInterface $dispatcher,
        RequestStack $requestStack,
        CsrfTokenManagerInterface $csrfTokenManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->encoder           = $encoder;
        $this->dispatcher        = $dispatcher;
        $this->integrationHelper = $integrationHelper;
        $this->requestStack      = $requestStack;
        $this->csrfTokenManager  = $csrfTokenManager;
        $this->urlGenerator      = $urlGenerator;
    }

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod(Request::METHOD_POST);
    }

    /**
     * @return array<string, mixed|null>
     */
    public function getCredentials(Request $request): array
    {
        $credentials = [
            'username'    => $request->request->get('username'),
            'password'    => $request->request->get('password'),
            'csrf_token'  => $request->request->get('_csrf_token'),
            'integration' => $request->get('integration'),
        ];

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['username']);

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider): ?User
    {
        $csrfToken = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new InvalidCsrfTokenException();
        }

        try {
            $user = $userProvider->loadUserByUsername($credentials['username']);
        } catch (UsernameNotFoundException $e) {
            $user = $credentials['username'];
        }

        $this->authenticatingService = $credentials['integration'] ?? null;

        // Try authenticating with a plugin first
        if ($this->dispatcher->hasListeners(UserEvents::USER_FORM_AUTHENTICATION)) {
            $integrations = $this->integrationHelper->getIntegrationObjects($this->authenticatingService, ['sso_form'], false, null, true);
            $authEvent    = new AuthenticationEvent(
                $user,
                new PluginToken(
                    null, // In 4.4 there was a provider key. If the issue will be severe we need to override whole guard. Otherwise, wait for Symfony 5.2 and Passport.
                    $this->authenticatingService,
                    $user,
                    ($user instanceof User) ? $user->getPassword() : '',
                    ($user instanceof User) ? $user->getRoles() : [],
                    $this->authEventResponse // though this will be null ?
                ),
                $userProvider,
                $this->requestStack->getCurrentRequest(),
                false,
                $this->authenticatingService,
                $integrations
            );
            $this->dispatcher->dispatch($authEvent, UserEvents::USER_FORM_AUTHENTICATION);

            if ($authEvent->isAuthenticated()) {
                $user                        = $authEvent->getUser();
                $this->authenticatingService = $authEvent->getAuthenticatingService();
            } elseif ($authEvent->isFailed()) {
                throw new AuthenticationException($authEvent->getFailedAuthenticationMessage());
            }

            $this->authEventResponse = $authEvent->getResponse();
        }

        if (!$user instanceof User) {
            throw new BadCredentialsException();
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return $this->encoder->isPasswordValid($user, $credentials['password']);
    }

    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?RedirectResponse
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        // If integrations fail due to redirect to dashboard look into
        // how to detect if that's a proper form auth and return null if request must continue w/o redirect
        return new RedirectResponse($this->urlGenerator->generate('mautic_dashboard_index'));
    }

    protected function getLoginUrl(): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
