<?php

namespace App\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MeController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[CurrentUser] ?SecurityUser $user): Response
    {
        if (null === $user) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->userId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'tenantId' => $user->tenantId(),
        ], Response::HTTP_OK);
    }
}
