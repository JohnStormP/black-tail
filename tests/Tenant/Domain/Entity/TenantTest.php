<?php

namespace App\Tests\Tenant\Domain\Entity;

use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\InvalidTenantNameException;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantStatus;
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function testRegisterCreatesActiveTenant(): void
    {
        $id = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $tenant = Tenant::register($id, 'Casino Alpha', $createdAt);

        $this->assertTrue($tenant->id()->equals($id));
        $this->assertSame('Casino Alpha', $tenant->name());
        $this->assertSame(TenantStatus::Active, $tenant->status());
        $this->assertTrue($tenant->isActive());
        $this->assertEquals($createdAt, $tenant->createdAt());
    }

    public function testRegisterRejectsEmptyName(): void
    {
        $this->expectException(InvalidTenantNameException::class);

        Tenant::register(
            TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            '   ',
        );
    }

    public function testSuspendAndActivate(): void
    {
        $tenant = Tenant::register(
            TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            'Casino Alpha',
        );

        $tenant->suspend();
        $this->assertSame(TenantStatus::Suspended, $tenant->status());
        $this->assertFalse($tenant->isActive());

        $tenant->activate();
        $this->assertSame(TenantStatus::Active, $tenant->status());
        $this->assertTrue($tenant->isActive());
    }

    public function testRename(): void
    {
        $tenant = Tenant::register(
            TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            'Casino Alpha',
        );

        $tenant->rename('Casino Beta');

        $this->assertSame('Casino Beta', $tenant->name());
    }
}
