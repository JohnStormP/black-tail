<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Application\Handler\StartCrashRoundHandler;
use App\Game\Domain\Exception\CrashRoundNotFoundException;
use App\Game\Domain\Exception\InvalidCrashRoundIdException;
use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class StartCrashRoundController
{
    public function __construct(
        private readonly StartCrashRoundHandler $startCrashRound,
        private readonly CrashRoundRepositoryInterface $rounds,
    ) {
    }

    #[Route('/api/crash/rounds/{roundId}/start', name: 'api_crash_rounds_start', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function __invoke(string $roundId): Response
    {
        try {
            $id = CrashRoundId::fromString($roundId);
            $this->rounds->getById($id);
            ($this->startCrashRound)(new StartCrashRoundCommand($id->toString()));
        } catch (InvalidCrashRoundIdException|InvalidCrashRoundTransitionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (CrashRoundNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        $round = $this->rounds->getById($id);

        return new JsonResponse(CrashRoundJson::from($round));
    }
}
