<?php

namespace App\Tests\Game\Domain\Entity;

use App\Game\Domain\Entity\TenantGame;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\TenantGameId;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantGameTest extends TestCase
{
    private const string TENANT_GAME_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_ID = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string GAME_ID = '01CXYZ00000000000000000001';

    public function testAssignCreatesEnabledLink(): void
    {
        $createdAt = new \DateTimeImmutable('2026-09-28 12:00:00');

        $link = TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
            $createdAt,
        );

        $this->assertTrue($link->id()->equals(TenantGameId::fromString(self::TENANT_GAME_ID)));
        $this->assertTrue($link->tenantId()->equals(TenantId::fromString(self::TENANT_ID)));
        $this->assertTrue($link->gameId()->equals(GameId::fromString(self::GAME_ID)));
        $this->assertTrue($link->isEnabled());
        $this->assertEquals($createdAt, $link->createdAt());
    }

    public function testDisableAndEnable(): void
    {
        $link = TenantGame::assign(
            TenantGameId::fromString(self::TENANT_GAME_ID),
            TenantId::fromString(self::TENANT_ID),
            GameId::fromString(self::GAME_ID),
        );

        $link->disable();
        $this->assertFalse($link->isEnabled());

        $link->enable();
        $this->assertTrue($link->isEnabled());
    }
}
