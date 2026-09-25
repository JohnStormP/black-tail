<?php

namespace App\Wallet\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Wallet\Application\Handler\GetWalletHandler;
use App\Wallet\Application\Query\GetWalletQuery;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetWalletController
{
    public function __construct(
        private readonly GetWalletHandler $getWallet,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/wallets/{walletId}', name: 'api_wallets_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $walletId): Response
    {
        $wallet = ($this->getWallet)(new GetWalletQuery($walletId));
        $player = $this->players->getById($wallet->playerId());

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        return new JsonResponse([
            'id' => $wallet->id()->toString(),
            'playerId' => $wallet->playerId()->toString(),
            'currency' => $wallet->currency()->value,
            'available' => $wallet->availableBalance()->amount(),
            'reserved' => $wallet->reservedBalance()->amount(),
            'status' => $wallet->status()->value,
        ], Response::HTTP_OK);
    }
}
