<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\CashOutCrashBetCommand;
use App\Game\Application\Handler\CashOutCrashBetHandler;
use App\Game\Domain\Exception\CrashBetNotFoundException;
use App\Game\Domain\Exception\CrashRoundNotFoundException;
use App\Game\Domain\Exception\InvalidCrashBetException;
use App\Game\Domain\Exception\InvalidCrashBetIdException;
use App\Game\Domain\Exception\InvalidCrashBetTransitionException;
use App\Game\Domain\Exception\InvalidCrashMultiplierException;
use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashBetStatus;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CashOutCrashBetController
{
    public function __construct(
        private readonly CashOutCrashBetHandler $cashOutCrashBet,
        private readonly CrashBetRepositoryInterface $bets,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/crash/bets/{betId}/cash-out', name: 'api_crash_bets_cash_out', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $betId, #[MapRequestPayload] CashOutCrashBetRequest $body): Response
    {
        try {
            $id = CrashBetId::fromString($betId);
            $bet = $this->bets->getById($id);
            $player = $this->players->getById($bet->playerId());
        } catch (InvalidCrashBetIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (CrashBetNotFoundException|PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        if (CrashBetStatus::CashedOut === $bet->status()) {
            return new JsonResponse(CrashBetJson::from($bet), Response::HTTP_OK);
        }

        try {
            ($this->cashOutCrashBet)(new CashOutCrashBetCommand(
                $id->toString(),
                $body->settleEntryId,
                $body->payoutEntryId,
            ));
        } catch (InvalidCrashBetException|InvalidCrashBetTransitionException|InvalidCrashMultiplierException|InvalidCrashRoundTransitionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (CrashRoundNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (LedgerIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $bet = $this->bets->getById($id);

        return new JsonResponse(CrashBetJson::from($bet), Response::HTTP_OK);
    }
}
