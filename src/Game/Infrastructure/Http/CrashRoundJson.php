<?php

namespace App\Game\Infrastructure\Http;

use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\ValueObject\CrashRoundStatus;

final class CrashRoundJson
{
    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     createdAt: string,
     *     startedAt: string|null,
     *     crashedAt: string|null,
     *     crashPointHundredths?: int,
     * }
     */
    public static function from(CrashRound $round): array
    {
        $payload = [
            'id' => $round->id()->toString(),
            'status' => $round->status()->value,
            'createdAt' => $round->createdAt()->format(\DateTimeInterface::ATOM),
            'startedAt' => $round->startedAt()?->format(\DateTimeInterface::ATOM),
            'crashedAt' => $round->crashedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if (CrashRoundStatus::Crashed === $round->status()) {
            $payload['crashPointHundredths'] = $round->crashPoint()->toHundredths();
        }

        return $payload;
    }
}
