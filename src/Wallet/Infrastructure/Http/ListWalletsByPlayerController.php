<?php

namespace App\Wallet\Infrastructure\Http;

use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ListWalletsByPlayerController
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/wallets', name: 'api_wallets_list_by_player', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): Response
    {
        $rawPlayerId = $request->query->get('playerId');
        if (empty($rawPlayerId)) {
            throw new BadRequestHttpException('playerId query is required');
        }

        try {
            $playerId = PlayerId::fromString($rawPlayerId);
            $player = $this->players->getById($playerId);
        } catch (InvalidPlayerIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        $items = $this->wallets->findByPlayerId($playerId);

        return new JsonResponse(array_map(WalletJson::from(...), $items), Response::HTTP_OK);
    }
}
