<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Command\OpenCrashRoundCommand;
use App\Game\Application\Handler\OpenCrashRoundHandler;
use App\Game\Domain\Exception\InvalidCrashRoundIdException;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OpenCrashRoundController
{
    public function __construct(
        private readonly OpenCrashRoundHandler $openCrashRound,
        private readonly CrashRoundRepositoryInterface $rounds,
    ) {
    }

    #[Route('/api/crash/rounds', name: 'api_crash_rounds_open', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function __invoke(#[MapRequestPayload] OpenCrashRoundRequest $body): Response
    {
        try {
            $id = CrashRoundId::fromString($body->roundId);
        } catch (InvalidCrashRoundIdException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->rounds->findById($id);

        if (null !== $existing) {
            return new JsonResponse(CrashRoundJson::from($existing), Response::HTTP_OK);
        }

        try {
            ($this->openCrashRound)(new OpenCrashRoundCommand($id->toString()));
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->rounds->findById($id);

            if (null !== $existing) {
                return new JsonResponse(CrashRoundJson::from($existing), Response::HTTP_OK);
            }

            return new JsonResponse(['error' => 'Crash round cannot be found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $round = $this->rounds->getById($id);

        return new JsonResponse(CrashRoundJson::from($round), Response::HTTP_CREATED);
    }
}
