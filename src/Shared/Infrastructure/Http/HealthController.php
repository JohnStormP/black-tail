<?php

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }
}
