<?php

namespace App\Tests\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidUserEmailException;
use App\Identity\Domain\ValueObject\UserEmail;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    public function testFromStringNormalizesCase(): void
    {
        $email = UserEmail::fromString('Admin@Example.COM');

        $this->assertSame('admin@example.com', $email->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidUserEmailException::class);

        UserEmail::fromString('   ');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidUserEmailException::class);

        UserEmail::fromString('not-an-email');
    }
}
