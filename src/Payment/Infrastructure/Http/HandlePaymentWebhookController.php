<?php

namespace App\Payment\Infrastructure\Http;

use App\Payment\Application\Command\HandlePaymentWebhookCommand;
use App\Payment\Application\Handler\HandlePaymentWebhookHandler;
use App\Payment\Domain\Exception\InvalidPaymentException;
use App\Payment\Domain\Exception\InvalidPaymentWebhookSignatureException;
use App\Payment\Domain\Exception\PaymentNotFoundException;
use App\Payment\Infrastructure\Security\PaymentWebhookSignatureVerifier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class HandlePaymentWebhookController
{
    public function __construct(
        private readonly HandlePaymentWebhookHandler $handleWebhook,
        private readonly PaymentWebhookSignatureVerifier $signatureVerifier,
    ) {
    }

    #[Route('/api/payments/webhooks/{provider}', name: 'api_payments_webhooks', methods: ['POST'])]
    public function __invoke(
        string $provider,
        Request $request,
        #[MapRequestPayload] HandlePaymentWebhookRequest $body,
    ): Response {
        $rawBody = $request->getContent();
        $signature = $request->headers->get('X-Signature');
        try {
            $this->signatureVerifier->assertValid($rawBody, $signature);
        } catch (InvalidPaymentWebhookSignatureException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

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
