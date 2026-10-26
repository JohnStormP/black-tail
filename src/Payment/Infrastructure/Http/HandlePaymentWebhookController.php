<?php

namespace App\Payment\Infrastructure\Http;

use App\Payment\Application\Command\HandlePaymentWebhookCommand;
use App\Payment\Application\Handler\HandlePaymentWebhookHandler;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class HandlePaymentWebhookController
{
    public function __construct(
        private readonly HandlePaymentWebhookHandler $handleWebhook,
    ) {
    }

    #[Route('/api/payments/webhooks/{provider}', name: 'api_payments_webhooks', methods: ['POST'])]
    public function __invoke(string $provider, #[MapRequestPayload] HandlePaymentWebhookRequest $body): Response
    {
        try {
            ($this->handleWebhook)(new HandlePaymentWebhookCommand(
                $provider,
                $body->providerReference,
                $body->outcome,
                $body->ledgerEntryId,
                $body->releaseEntryId,
                $body->failureReason,
            ));
        } catch (PaymentNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (InvalidPaymentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
