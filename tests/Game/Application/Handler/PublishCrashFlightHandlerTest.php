<?php

namespace App\Tests\Game\Application\Handler;

use App\Game\Application\Command\OpenCrashRoundCommand;
use App\Game\Application\Command\PublishCrashFlightCommand;
use App\Game\Application\Command\StartCrashRoundCommand;
use App\Game\Application\Handler\OpenCrashRoundHandler;
use App\Game\Application\Handler\PublishCrashFlightHandler;
use App\Game\Application\Handler\StartCrashRoundHandler;
use App\Game\Application\Realtime\CrashRealtimePublisherInterface;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\Service\CrashEngine;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PublishCrashFlightHandlerTest extends KernelTestCase
{
    private const string ROUND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function testPublishesTicksBelowCrashPoint(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.CrashRound::class)->execute();

        $rounds = self::getContainer()->get(CrashRoundRepositoryInterface::class);
        self::assertInstanceOf(CrashRoundRepositoryInterface::class, $rounds);

        $transactions = self::getContainer()->get(TransactionManagerInterface::class);
        self::assertInstanceOf(TransactionManagerInterface::class, $transactions);

        $realtime = new class implements CrashRealtimePublisherInterface {
            /** @var list<int> */
            public array $ticks = [];

            public function publishStarted(CrashRoundId $roundId): void
            {
            }

            public function publishTick(CrashRoundId $roundId, int $hundredths): void
            {
                $this->ticks[] = $hundredths;
            }

            public function publishCrashed(CrashRoundId $roundId, int $crashPointHundredths): void
            {
                Assert::fail('Flight publisher must not crash the round');
            }
        };

        $open = new OpenCrashRoundHandler($rounds, new CrashEngine(), new SeededRandomIntGenerator(42));
        $start = new StartCrashRoundHandler($rounds, $transactions, $realtime, new \Symfony\Component\Clock\MockClock());
        ($open)(new OpenCrashRoundCommand(self::ROUND_ID));
        ($start)(new StartCrashRoundCommand(self::ROUND_ID));

        $round = $rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $crashPoint = $round->crashPoint()->toHundredths();
        $handler = new PublishCrashFlightHandler($rounds, $realtime);

        ($handler)(new PublishCrashFlightCommand(self::ROUND_ID, 5));
        $this->assertNotEmpty($realtime->ticks);
        $this->assertSame(100, $realtime->ticks[0]);
        foreach ($realtime->ticks as $tick) {
            $this->assertLessThan($crashPoint, $tick);
        }
        $expected = [];

        for ($i = 100; $i < $crashPoint; $i += 5) {
            $expected[] = $i;
        }

        $this->assertSame($expected, $realtime->ticks);
    }
}
