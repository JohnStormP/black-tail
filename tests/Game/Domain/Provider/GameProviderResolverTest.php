<?php

namespace App\Tests\Game\Domain\Provider;

use App\Game\Domain\Exception\GameProviderNotFoundException;
use App\Game\Domain\Provider\GameProviderResolver;
use App\Game\Domain\Service\LuckyReelsEngine;
use App\Game\Domain\ValueObject\GameCode;
use App\Game\Infrastructure\Provider\LuckyReelsGameProvider;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use PHPUnit\Framework\TestCase;

final class GameProviderResolverTest extends TestCase
{
    public function testResolvesLuckyReelsProvider(): void
    {
        $resolver = new GameProviderResolver([
            new LuckyReelsGameProvider(new LuckyReelsEngine()),
        ]);

        $provider = $resolver->resolve(GameCode::fromString('lucky_reels'));

        $this->assertInstanceOf(LuckyReelsGameProvider::class, $provider);
        $this->assertTrue($provider->supports(GameCode::fromString('lucky_reels')));
    }

    public function testSpinIsDeterministicWithSeed(): void
    {
        $provider = new LuckyReelsGameProvider(new LuckyReelsEngine());

        $a = $provider->spin(new SeededRandomIntGenerator(7));
        $b = $provider->spin(new SeededRandomIntGenerator(7));

        $this->assertSame($a->stops(), $b->stops());
        $this->assertSame($a->totalMultiplier(), $b->totalMultiplier());
    }

    public function testThrowsWhenNoProviderSupportsCode(): void
    {
        $resolver = new GameProviderResolver([
            new LuckyReelsGameProvider(new LuckyReelsEngine()),
        ]);

        $this->expectException(GameProviderNotFoundException::class);

        $resolver->resolve(GameCode::fromString('crash'));
    }

    public function testThrowsWhenProviderListEmpty(): void
    {
        $resolver = new GameProviderResolver([]);

        $this->expectException(GameProviderNotFoundException::class);

        $resolver->resolve(GameCode::fromString('lucky_reels'));
    }
}
