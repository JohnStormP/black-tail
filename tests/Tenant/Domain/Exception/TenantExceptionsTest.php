<?php

namespace App\Tests\Tenant\Domain\Exception;

use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\Exception\InvalidTenantNameException;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class TenantExceptionsTest extends TestCase
{
    public function testInvalidTenantIdEmpty(): void
    {
        $exception = InvalidTenantIdException::empty();

        $this->assertNotSame('', $exception->getMessage());
    }

    public function testTenantNotFoundIncludesId(): void
    {
        $id = TenantId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $exception = TenantNotFoundException::withId($id);

        $this->assertStringContainsString($id->toString(), $exception->getMessage());
    }

    public function testInvalidTenantNameEmpty(): void
    {
        $exception = InvalidTenantNameException::empty();

        $this->assertNotSame('', $exception->getMessage());
    }
}
