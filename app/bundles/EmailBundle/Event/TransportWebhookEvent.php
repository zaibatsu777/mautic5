<?php

namespace Mautic\EmailBundle\Event;

use Mautic\EmailBundle\Swiftmailer\Transport\CallbackTransportInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event triggered when a transport service send Mautic a webhook request.
 */
class TransportWebhookEvent extends Event
{
    /**
     * @var CallbackTransportInterface
     */
    private $transport;

    /**
     * @var Request
     */
    private $request;

    public function __construct(CallbackTransportInterface $transport, Request $request)
    {
        $this->transport = $transport;
        $this->request   = $request;
    }

    /**
     * @return CallbackTransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Checks if the event is for specific transport.
     *
     * @param string $transportClassName
     *
     * @return bool
     */
    public function transportIsInstanceOf($transportClassName)
    {
        return $this->transport instanceof $transportClassName;
    }
}
