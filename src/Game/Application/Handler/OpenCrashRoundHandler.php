<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\OpenCrashRoundCommand;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;

final class OpenCrashRoundHandler
{
    public function __construct(
        private readonly CrashRoundRepositoryInterface $rounds,
        private readonly CrashEngine $engine,
        private readonly RandomIntGeneratorInterface $rng,
    ) {
    }

    public function __invoke(OpenCrashRoundCommand $command): void
    {
        $roundId = CrashRoundId::fromString($command->roundId);
        $crashPoint = $this->engine->rollCrashPoint($this->rng);
        $round = CrashRound::open($roundId, $crashPoint);
        $this->rounds->save($round);
    }
}
