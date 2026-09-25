<?php

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Expected SecurityUser after login');
        }

        return new JsonResponse([
            'id' => $user->userId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'tenantId' => $user->tenantId(),
        ], Response::HTTP_OK);
    }
}
