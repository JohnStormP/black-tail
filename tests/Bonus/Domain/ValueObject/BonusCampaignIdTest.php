<?php

namespace App\Tests\Bonus\Domain\ValueObject;

use App\Bonus\Domain\Exception\InvalidBonusCampaignIdException;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use PHPUnit\Framework\TestCase;

final class BonusCampaignIdTest extends TestCase
{
    public function testFromStringAcceptsUlid(): void
    {
        $id = BonusCampaignId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringNormalizesToUppercase(): void
    {
        $id = BonusCampaignId::fromString('01arz3ndektsv4rrffq69g5fav');

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $id->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidBonusCampaignIdException::class);

        BonusCampaignId::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidBonusCampaignIdException::class);

        BonusCampaignId::fromString('not-a-ulid');
    }

    public function testEquals(): void
    {
        $a = BonusCampaignId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $b = BonusCampaignId::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $c = BonusCampaignId::fromString('01BX5ZZKBKACTAV9WEVGEMMVRY');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}
