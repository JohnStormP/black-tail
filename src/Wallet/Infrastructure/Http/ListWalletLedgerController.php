<?php

namespace App\Wallet\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Wallet\Application\Handler\ListWalletLedgerHandler;
use App\Wallet\Application\Query\ListWalletLedgerQuery;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListWalletLedgerController
{
    public function __construct(
        private readonly ListWalletLedgerHandler $listWalletLedger,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/wallets/{walletId}/ledger', name: 'api_wallets_ledger', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $walletId): JsonResponse
    {
        $result = ($this->listWalletLedger)(new ListWalletLedgerQuery($walletId));
        $wallet = $result['wallet'];
        $entries = $result['entries'];
        $player = $this->players->getById($wallet->playerId());
        if (!$this->security->isGranted(
            TenantAccessVoter::TENANT_ACCESS,
            $player->tenantId(),
        )) {
            throw new AccessDeniedHttpException();
        }
        $items = [];
        foreach ($entries as $entry) {
            $items[] = [
                'id' => $entry->id()->toString(),
                'type' => $entry->type()->value,
                'amount' => $entry->amount()->amount(),
                'currency' => $entry->currency()->value,
                'balanceAfter' => $entry->balanceAfter()->amount(),
                'createdAt' => $entry->createdAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse([
            'walletId' => $wallet->id()->toString(),
            'items' => $items,
        ], Response::HTTP_OK);
    }
}
