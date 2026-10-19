<?php

namespace App\Tests\Risk\Domain\ValueObject;

use App\Risk\Domain\Exception\InvalidRiskRuleIdException;
use App\Risk\Domain\ValueObject\RiskRuleId;
use PHPUnit\Framework\TestCase;

final class RiskRuleIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = RiskRuleId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = RiskRuleId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidRiskRuleIdException::class);

        RiskRuleId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidRiskRuleIdException::class);

        RiskRuleId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = RiskRuleId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = RiskRuleId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = RiskRuleId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
