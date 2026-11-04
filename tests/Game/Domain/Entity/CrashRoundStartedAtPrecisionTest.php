<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\CrashBet;
use App\Game\Domain\Entity\CrashRound;
use App\Game\Domain\Repository\CrashRoundRepositoryInterface;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CrashRoundStartedAtPrecisionTest extends KernelTestCase
{
    private const string ROUND_ID = '01JC9K2M5N7P8Q3R4S6T7V8CR9';

    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.CrashBet::class)->execute();
        $em->createQuery('DELETE FROM '.CrashRound::class)->execute();
    }

    public function testReloadedStartedAtKeepsMicrosecondsForFlightElapsed(): void
    {
        $rounds = self::getContainer()->get(CrashRoundRepositoryInterface::class);
        self::assertInstanceOf(CrashRoundRepositoryInterface::class, $rounds);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $round = CrashRound::open(
            CrashRoundId::fromString(self::ROUND_ID),
            CrashMultiplier::fromHundredths(500),
        );
        $startedAt = new \DateTimeImmutable('2026-11-04 18:40:00.800000');
        $round->start($startedAt);
        $rounds->save($round);
        $em->flush();
        $em->clear();

        $reloaded = $rounds->getById(CrashRoundId::fromString(self::ROUND_ID));
        $this->assertSame('800', $reloaded->startedAt()?->format('v'));

        $now = new \DateTimeImmutable('2026-11-04 18:40:00.850000');
        $this->assertSame(101, $reloaded->currentMultiplierAt($now)->toHundredths());
    }
}
