<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\PlaceCrashBetCommand;
use App\Game\Application\Handler\PlaceCrashBetHandler;
use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Exception\CrashRoundNotFoundException;
use App\Game\Domain\Exception\InvalidCrashBetException;
use App\Game\Domain\Exception\InvalidCrashBetIdException;
use App\Game\Domain\Exception\InvalidCrashRoundIdException;
use App\Game\Domain\Repository\CrashBetRepositoryInterface;
use App\Game\Domain\ValueObject\CrashBetId;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Identity\Infrastructure\Security\TenantAccessVoter;
use App\Player\Domain\Exception\InvalidPlayerIdException;
use App\Player\Domain\Exception\PlayerNotFoundException;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Wallet\Domain\Exception\InvalidWalletIdException;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Exception\WalletNotFoundException;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PlaceCrashBetController
{
    public function __construct(
        private readonly PlaceCrashBetHandler $placeCrashBet,
        private readonly CrashBetRepositoryInterface $bets,
        private readonly PlayerRepositoryInterface $players,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/crash/bets', name: 'api_crash_bets_place', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[MapRequestPayload] PlaceCrashBetRequest $body): Response
    {
        try {
            $crashBetId = CrashBetId::fromString($body->betId);
            $crashRoundId = CrashRoundId::fromString($body->roundId);
            $playerId = PlayerId::fromString($body->playerId);
            $walletId = WalletId::fromString($body->walletId);
            $player = $this->players->getById($playerId);
        } catch (InvalidCrashBetIdException|InvalidCrashRoundIdException|InvalidPlayerIdException|InvalidWalletIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PlayerNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
        if (!$this->security->isGranted(TenantAccessVoter::TENANT_ACCESS, $player->tenantId())) {
            throw new AccessDeniedHttpException();
        }

        $existing = $this->bets->findById($crashBetId);
        if (null !== $existing) {
            if ($this->matchesRequest($existing, $crashRoundId, $playerId, $walletId, $body->amountMinor, $body->currency)) {
                return new JsonResponse(CrashBetJson::from($existing), Response::HTTP_OK);
            }

            return new JsonResponse(['error' => 'Crash bet already exists'], Response::HTTP_CONFLICT);
        }

        try {
            ($this->placeCrashBet)(new PlaceCrashBetCommand(
                $crashBetId->toString(),
                $crashRoundId->toString(),
                $playerId->toString(),
                $walletId->toString(),
                $body->reserveEntryId,
                $body->amountMinor,
                $body->currency
            ));
        } catch (InvalidCrashBetException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (CrashRoundNotFoundException|WalletNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (LedgerIdempotencyConflictException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->bets->findById($crashBetId);
            if (null !== $existing) {
                if ($this->matchesRequest($existing, $crashRoundId, $playerId, $walletId, $body->amountMinor, $body->currency)) {
                    return new JsonResponse(CrashBetJson::from($existing), Response::HTTP_OK);
                }

                return new JsonResponse(['error' => 'Crash bet already exists'], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(['error' => 'Crash bet cannot be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $bet = $this->bets->getById($crashBetId);

        return new JsonResponse(CrashBetJson::from($bet), Response::HTTP_CREATED);
    }

    private function matchesRequest(
        CrashBet $existing,
        CrashRoundId $roundId,
        PlayerId $playerId,
        WalletId $walletId,
        int $amountMinor,
        string $currency,
    ): bool {
        $amount = Money::of($amountMinor, Currency::from($currency));

        return $existing->roundId()->equals($roundId)
            && $existing->playerId()->equals($playerId)
            && $existing->walletId()->equals($walletId)
            && $existing->amount()->equals($amount);
    }
}
