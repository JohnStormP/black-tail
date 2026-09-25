<?php

namespace App\Tests\Shared\Infrastructure\Random;

use App\Shared\Domain\Exception\InvalidRandomRangeException;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;
use App\Shared\Infrastructure\Random\PhpRandomIntGenerator;
use App\Shared\Infrastructure\Random\SeededRandomIntGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RandomIntGeneratorTest extends KernelTestCase
{
    public function testPhpGeneratorIsWiredAsAlias(): void
    {
        self::bootKernel();

        $generator = self::getContainer()->get(RandomIntGeneratorInterface::class);
        self::assertInstanceOf(PhpRandomIntGenerator::class, $generator);
    }

    public function testPhpGeneratorReturnsValueWithinRange(): void
    {
        $generator = new PhpRandomIntGenerator();

        for ($i = 0; $i < 20; ++$i) {
            $value = $generator->nextInt(0, 19);
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(19, $value);
        }
    }

    public function testPhpGeneratorRejectsInvalidRange(): void
    {
        $this->expectException(InvalidRandomRangeException::class);

        (new PhpRandomIntGenerator())->nextInt(5, 1);
    }

    public function testSeededIsDeterministic(): void
    {
        $a = new SeededRandomIntGenerator(42);
        $b = new SeededRandomIntGenerator(42);

        $seqA = [$a->nextInt(0, 19), $a->nextInt(0, 19), $a->nextInt(0, 19), $a->nextInt(0, 19), $a->nextInt(0, 19)];
        $seqB = [$b->nextInt(0, 19), $b->nextInt(0, 19), $b->nextInt(0, 19), $b->nextInt(0, 19), $b->nextInt(0, 19)];

        $this->assertSame($seqA, $seqB);
    }

    public function testDifferentSeedsDiverge(): void
    {
        $a = new SeededRandomIntGenerator(1);
        $b = new SeededRandomIntGenerator(2);

        $seqA = [$a->nextInt(0, 1000), $a->nextInt(0, 1000), $a->nextInt(0, 1000)];
        $seqB = [$b->nextInt(0, 1000), $b->nextInt(0, 1000), $b->nextInt(0, 1000)];

        $this->assertNotSame($seqA, $seqB);
    }

    public function testSeededRejectsInvalidRange(): void
    {
        $this->expectException(InvalidRandomRangeException::class);

        (new SeededRandomIntGenerator(1))->nextInt(10, 0);
    }
}
