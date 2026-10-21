<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Application\Realtime\CrashMercureTopic;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GetCrashRoundStreamController
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly HubInterface $hub,
    ) {
    }

    #[Route('/api/crash/rounds/{roundId}/stream', name: 'api_crash_round_stream', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(string $roundId): Response
    {
        $id = CrashRoundId::fromString($roundId);
        $round = $this->rounds->getById($id);

        return new JsonResponse([
            'roundId' => $round->id()->toString(),
            'status' => $round->status()->value,
            'mercurePublicUrl' => $this->hub->getPublicUrl(),
            'topic' => CrashMercureTopic::forRound($round->id()),
        ]);
    }
}
