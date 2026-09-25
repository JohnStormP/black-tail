<?php

namespace App\Tests\Identity\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\InvalidUserException;
use App\Identity\Domain\ValueObject\UserEmail;
use App\Identity\Domain\ValueObject\UserId;
use App\Identity\Domain\ValueObject\UserRole;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterSuperAdminWithoutTenant(): void
    {
        $id = UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $user = User::register(
            $id,
            UserEmail::fromString('super@example.com'),
            'hashed-password',
            UserRole::SuperAdmin,
            null,
            $createdAt,
        );

        $this->assertTrue($user->id()->equals($id));
        $this->assertSame('super@example.com', $user->email()->toString());
        $this->assertSame('hashed-password', $user->passwordHash());
        $this->assertSame(UserRole::SuperAdmin, $user->role());
        $this->assertNull($user->tenantId());
        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->isActive());
        $this->assertEquals($createdAt, $user->createdAt());
    }

    public function testRegisterTenantAdminRequiresTenant(): void
    {
        $tenantId = TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $user = User::register(
            UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            UserEmail::fromString('admin@example.com'),
            'hashed-password',
            UserRole::TenantAdmin,
            $tenantId,
        );

        $this->assertTrue($user->tenantId()?->equals($tenantId));
        $this->assertSame(UserRole::TenantAdmin, $user->role());
    }

    public function testRegisterSuperAdminRejectsTenant(): void
    {
        $this->expectException(InvalidUserException::class);

        User::register(
            UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            UserEmail::fromString('super@example.com'),
            'hashed-password',
            UserRole::SuperAdmin,
            TenantId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY'),
        );
    }

    public function testRegisterTenantRoleRejectsMissingTenant(): void
    {
        $this->expectException(InvalidUserException::class);

        User::register(
            UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            UserEmail::fromString('admin@example.com'),
            'hashed-password',
            UserRole::TenantAdmin,
            null,
        );
    }

    public function testRegisterRejectsEmptyPasswordHash(): void
    {
        $this->expectException(InvalidUserException::class);

        User::register(
            UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            UserEmail::fromString('super@example.com'),
            '   ',
            UserRole::SuperAdmin,
        );
    }

    public function testDisableAndActivate(): void
    {
        $user = User::register(
            UserId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            UserEmail::fromString('super@example.com'),
            'hashed-password',
            UserRole::SuperAdmin,
        );

        $user->disable();
        $this->assertSame(UserStatus::Disabled, $user->status());
        $this->assertFalse($user->isActive());

        $user->activate();
        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->isActive());
    }
}
