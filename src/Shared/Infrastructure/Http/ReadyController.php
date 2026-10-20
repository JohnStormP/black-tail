<?php

namespace App\Shared\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReadyController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route('/ready', name: 'ready', methods: ['GET'])]
    public function __invoke(): Response
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse([
                'status' => 'not_ready',
                'error' => 'database',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(['status' => 'ready'], Response::HTTP_OK);
    }
}
