<?php

namespace App\Tests\Player\Domain\ValueObject;

use App\Player\Domain\Exception\InvalidPlayerEmailException;
use App\Player\Domain\ValueObject\PlayerEmail;
use PHPUnit\Framework\TestCase;

final class PlayerEmailTest extends TestCase
{
    public function testFromStringNormalizesCase(): void
    {
        $email = PlayerEmail::fromString('Player@Example.COM');

        $this->assertSame('player@example.com', $email->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidPlayerEmailException::class);

        PlayerEmail::fromString('   ');
    }

    public function testFromStringRejectsInvalid(): void
    {
        $this->expectException(InvalidPlayerEmailException::class);

        PlayerEmail::fromString('not-an-email');
    }

    public function testEquals(): void
    {
        $a = PlayerEmail::fromString('a@example.com');
        $b = PlayerEmail::fromString('A@Example.com');
        $c = PlayerEmail::fromString('b@example.com');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
