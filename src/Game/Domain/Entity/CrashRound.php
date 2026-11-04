<?php

namespace App\Game\Domain\Entity;

use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\Service\CrashFlightCurve;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crash_rounds')]
class CrashRound
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(name: 'status', type: 'string', length: 32, enumType: CrashRoundStatus::class)]
    private CrashRoundStatus $status;

    #[ORM\Column(name: 'crash_point_hundredths', type: 'integer')]
    private int $crashPointHundredths;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable_micro', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'crashed_at', type: 'datetime_immutable_micro', nullable: true)]
    private ?\DateTimeImmutable $crashedAt = null;

    private function __construct()
    {
    }

    public static function open(
        CrashRoundId $id,
        CrashMultiplier $crashPoint,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): self {
        $round = new self();
        $round->id = $id->toString();
        $round->status = CrashRoundStatus::Waiting;
        $round->crashPointHundredths = $crashPoint->toHundredths();
        $round->createdAt = $createdAt;

        return $round;
    }

    public function id(): CrashRoundId
    {
        return CrashRoundId::fromString($this->id);
    }

    public function status(): CrashRoundStatus
    {
        return $this->status;
    }

    public function crashPoint(): CrashMultiplier
    {
        return CrashMultiplier::fromHundredths($this->crashPointHundredths);
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function crashedAt(): ?\DateTimeImmutable
    {
        return $this->crashedAt;
    }

    public function acceptsBets(): bool
    {
        return CrashRoundStatus::Waiting === $this->status;
    }

    public function start(\DateTimeImmutable $startedAt = new \DateTimeImmutable()): void
    {
        if (CrashRoundStatus::Waiting !== $this->status) {
            throw InvalidCrashRoundTransitionException::cannotTransition($this->status, CrashRoundStatus::Running);
        }

        $this->status = CrashRoundStatus::Running;
        $this->startedAt = $startedAt;
    }

    public function crash(\DateTimeImmutable $crashedAt = new \DateTimeImmutable()): void
    {
        if (CrashRoundStatus::Running !== $this->status) {
            throw InvalidCrashRoundTransitionException::cannotTransition($this->status, CrashRoundStatus::Crashed);
        }

        $this->status = CrashRoundStatus::Crashed;
        $this->crashedAt = $crashedAt;
    }

    public function isCashOutAllowed(CrashMultiplier $at): bool
    {
        if (CrashRoundStatus::Running !== $this->status) {
            return false;
        }

        return $at->isStrictlyBelow($this->crashPoint());
    }

    public function currentMultiplierAt(\DateTimeImmutable $now): CrashMultiplier
    {
        if (CrashRoundStatus::Running !== $this->status) {
            throw InvalidCrashRoundTransitionException::roundNotRunning($this->id, $this->status);
        }

        if (null === $this->startedAt) {
            throw InvalidCrashRoundTransitionException::roundMissingStartedAt($this->id);
        }

        $nowMs = $now->getTimestamp() * 1000 + (int) $now->format('v');
        $startedMs = $this->startedAt->getTimestamp() * 1000 + (int) $this->startedAt->format('v');
        $elapsedMs = (int) max(0, $nowMs - $startedMs);

        return CrashFlightCurve::atElapsedMs($elapsedMs, $this->crashPoint());
    }
}
