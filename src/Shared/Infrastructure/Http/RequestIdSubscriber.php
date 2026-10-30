<?php

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestIdSubscriber
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $incoming = trim((string) $request->headers->get(RequestId::HEADER));
        $requestId = RequestId::isValid($incoming) ? $incoming : RequestId::generate();

        $request->attributes->set(RequestId::ATTRIBUTE, $requestId);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -256)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get(RequestId::ATTRIBUTE);
        if (!\is_string($requestId) || '' === $requestId) {
            return;
        }

        $event->getResponse()->headers->set(RequestId::HEADER, $requestId);
    }
}
