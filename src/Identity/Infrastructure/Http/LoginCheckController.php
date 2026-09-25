<?php

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginCheckController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(): Response
    {
        return new JsonResponse(['message' => 'Login route is handled by security'], Response::HTTP_BAD_REQUEST);
    }
}
