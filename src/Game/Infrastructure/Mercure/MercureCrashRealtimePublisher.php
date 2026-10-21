<?php

namespace App\Game\Infrastructure\Mercure;

use App\Game\Application\Realtime\CrashMercureTopic;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\ValueObject\CrashRoundId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsAlias(CrashRealtimePublisherInterface::class, public: true)]
final class MercureCrashRealtimePublisher implements CrashRealtimePublisherInterface
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function publishStarted(CrashRoundId $roundId): void
    {
        $this->publish($roundId, 'crash.started', [
            'roundId' => $roundId->toString(),
            'type' => 'started',
        ]);
    }

    /**
     * @throws \JsonException
     */
    public function publishTick(CrashRoundId $roundId, int $hundredths): void
    {
        $this->publish($roundId, 'crash.tick', [
            'roundId' => $roundId->toString(),
            'type' => 'tick',
            'hundredths' => $hundredths,
        ]);
    }

    /**
     * @throws \JsonException
     */
    public function publishCrashed(CrashRoundId $roundId, int $crashPointHundredths): void
    {
        $this->publish($roundId, 'crash.crashed', [
            'roundId' => $roundId->toString(),
            'type' => 'crashed',
            'crashPointHundredths' => $crashPointHundredths,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \JsonException
     */
    private function publish(CrashRoundId $roundId, string $eventType, array $payload): void
    {
        $topic = CrashMercureTopic::forRound($roundId);
        $data = json_encode($payload, JSON_THROW_ON_ERROR);
        $update = new Update($topic, $data, false, null, $eventType);
        $this->hub->publish($update);
    }
}
