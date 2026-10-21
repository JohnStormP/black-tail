<?php

namespace App\Game\Domain\Entity;

use App\Game\Domain\Exception\InvalidCrashRoundTransitionException;
use App\Game\Domain\ValueObject\CrashMultiplier;
use App\Game\Domain\ValueObject\CrashRoundId;
use App\Game\Domain\ValueObject\CrashRoundStatus;

final class CrashRound
{
    private string $id;
    private CrashRoundStatus $status;
    private int $crashPointHundredths;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $startedAt = null;
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
}
