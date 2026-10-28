<?php

namespace App\Betting\Infrastructure\Http;

use App\Betting\Application\Command\SettleBetCommand;
use App\Betting\Application\Handler\SettleBetHandler;
use App\Betting\Domain\Exception\BetNotFoundException;
use App\Betting\Domain\Exception\InvalidBetException;
use App\Betting\Domain\Exception\InvalidBetIdException;
use App\Betting\Domain\Exception\InvalidBetTransitionException;
use App\Betting\Domain\Repository\BetRepositoryInterface;
use App\Betting\Domain\ValueObject\BetId;
use App\Betting\Domain\ValueObject\BetSettlementOutcome;
use App\Betting\Domain\ValueObject\BetStatus;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Wallet\Domain\Exception\InvalidWalletIdException;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SettleBetController
{
    public function __construct(
        private readonly SettleBetHandler $settleBet,
        private readonly BetRepositoryInterface $bets,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/bets/{betId}/settle', name: 'api_bets_settle', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $betId, #[MapRequestPayload] SettleBetRequest $body): Response
    {
        try {
            $id = BetId::fromString($betId);
            $bet = $this->bets->getById($id);
            $player = $this->players->getById($bet->playerId());
        } catch (InvalidBetIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (BetNotFoundException|PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        $outcome = BetSettlementOutcome::from($body->outcome);
        $targetStatus = BetSettlementOutcome::Won === $outcome ? BetStatus::Won : BetStatus::Lost;
        if ($targetStatus === $bet->status()) {
            return new JsonResponse(BetJson::from($bet), Response::HTTP_OK);
        }

        try {
            ($this->settleBet)(new SettleBetCommand(
                $betId,
                $body->walletId,
                $body->settleEntryId,
                $body->outcome,
                $body->payoutMinor,
                $body->payoutEntryId,
            ));
        } catch (InvalidBetException|InvalidBetTransitionException|InvalidWalletIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (WalletNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (LedgerIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $bet = $this->bets->getById($id);

        return new JsonResponse(BetJson::from($bet), Response::HTTP_OK);
    }
}
