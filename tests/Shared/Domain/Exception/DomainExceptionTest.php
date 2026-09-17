<?php

namespace App\Tests\Shared\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testIsThrowableDomainException(): void
    {
        $exception = new class('wallet blocked') extends DomainException {};

        $this->assertSame('wallet blocked', $exception->getMessage());
        $this->assertInstanceOf(\DomainException::class, $exception);
    }
}
