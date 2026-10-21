<?php

namespace App\Tests\Game\Infrastructure\Mercure;

use App\Game\Application\Realtime\CrashMercureTopic;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Infrastructure\Mercure\MercureCrashRealtimePublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class MercureCrashRealtimePublisherTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function testPublishTickSendsJsonUpdateToRoundTopic(): void
    {
        /** @var list<Update> $published */
        $published = [];
        $hub = new MockHub(
            'http://mercure.test/.well-known/mercure',
            new StaticTokenProvider('test-jwt'),
            static function (Update $update) use (&$published): string {
                $published[] = $update;

                return 'ok';
            },
        );
        $publisher = new MercureCrashRealtimePublisher($hub);
        $roundId = CrashRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $publisher->publishTick($roundId, 150);
        $this->assertCount(1, $published);
        $this->assertSame(
            [CrashMercureTopic::forRound($roundId)],
            $published[0]->getTopics(),
        );
        $this->assertSame('crash.tick', $published[0]->getType());
        $payload = json_decode($published[0]->getData(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('tick', $payload['type']);
        $this->assertSame(150, $payload['hundredths']);
        $this->assertSame($roundId->toString(), $payload['roundId']);
    }

    /**
     * @throws \JsonException
     */
    public function testPublishStartedAndCrashed(): void
    {
        /** @var list<Update> $published */
        $published = [];
        $hub = new MockHub(
            'http://mercure.test/.well-known/mercure',
            new StaticTokenProvider('test-jwt'),
            static function (Update $update) use (&$published): string {
                $published[] = $update;

                return 'ok';
            },
        );
        $publisher = new MercureCrashRealtimePublisher($hub);
        $roundId = CrashRoundId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $publisher->publishStarted($roundId);
        $publisher->publishCrashed($roundId, 250);
        $this->assertCount(2, $published);
        $this->assertSame('crash.started', $published[0]->getType());
        $this->assertSame('crash.crashed', $published[1]->getType());
        $crashed = json_decode($published[1]->getData(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(250, $crashed['crashPointHundredths']);
    }
}
