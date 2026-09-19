<?php

namespace App\Tests\Player\Domain\Entity;

use App\Player\Domain\Entity\Player;
use App\Player\Domain\ValueObject\PlayerEmail;
use App\Player\Domain\ValueObject\PlayerId;
use App\Player\Domain\ValueObject\PlayerStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testRegisterCreatesActivePlayer(): void
    {
        $playerId = PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $tenantId = TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');
        $email = PlayerEmail::fromString('player@example.com');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $player = Player::register($playerId, $tenantId, $email, $createdAt);

        $this->assertTrue($player->id()->equals($playerId));
        $this->assertTrue($player->tenantId()->equals($tenantId));
        $this->assertTrue($player->email()->equals($email));
        $this->assertSame(PlayerStatus::Active, $player->status());
        $this->assertTrue($player->isActive());
        $this->assertEquals($createdAt, $player->createdAt());
    }

    public function testBlockSuspendActivateSelfExclude(): void
    {
        $player = Player::register(
            PlayerId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
            PlayerEmail::fromString('player@example.com'),
        );

        $player->block();
        $this->assertSame(PlayerStatus::Blocked, $player->status());
        $this->assertFalse($player->isActive());

        $player->suspend();
        $this->assertSame(PlayerStatus::Suspended, $player->status());

        $player->selfExclude();
        $this->assertSame(PlayerStatus::SelfExcluded, $player->status());

        $player->activate();
        $this->assertSame(PlayerStatus::Active, $player->status());
        $this->assertTrue($player->isActive());
    }
}
