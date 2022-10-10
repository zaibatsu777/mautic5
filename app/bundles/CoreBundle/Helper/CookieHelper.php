<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\Helper;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CookieHelper implements EventSubscriberInterface
{
    private ?string $path;
    private ?string $domain;
    private bool $secure;
    private bool $httponly;
    private RequestStack $requestStack;
    private ?Request $request = null;

    /**
     * @var array<string, Cookie>
     */
    private array $cookies = [];

    public function __construct(string $cookiePath, ?string $cookieDomain, bool $cookieSecure, bool $cookieHttp, RequestStack $requestStack)
    {
        $this->path         = $cookiePath;
        $this->domain       = $cookieDomain;
        $this->secure       = $cookieSecure;
        $this->httponly     = $cookieHttp;
        $this->requestStack = $requestStack;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getCookie(string $key, $default = null)
    {
        if (null === $this->getRequest()) {
            return $default;
        }

        return $this->getRequest()->cookies->get($key, $default);
    }

    /**
     * @param int|string|float|bool|object|null $value
     */
    public function setCookie(string $name, $value, ?int $expire = 1800, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httponly = null): void
    {
        if (null !== $value) {
            $value = (string) $value;
        }

        $cookie = Cookie::create(
            $name,
            $value,
            null !== $expire ? time() + $expire : 0,
            $path ?? $this->path,
            $domain ?? $this->domain,
            $secure ?? $this->secure,
            $httponly ?? $this->httponly,
            false,
            ($secure ?? $this->secure) ? Cookie::SAMESITE_LAX : null
        );

        $this->cookies[$name] = $cookie;
    }

    /**
     * Deletes a cookie by expiring it.
     */
    public function deleteCookie(string $name, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httponly = null): void
    {
        $this->setCookie($name, '', -86400, $path, $domain, $secure, $httponly);
    }

    public function onResponse(ResponseEvent $event): void
    {
        foreach ($this->cookies as $cookie) {
            $event->getResponse()->headers->setCookie($cookie);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    private function getRequest(): ?Request
    {
        if (null !== $this->request) {
            return $this->request;
        }

        return $this->request = $this->requestStack->getMasterRequest();
    }
}
