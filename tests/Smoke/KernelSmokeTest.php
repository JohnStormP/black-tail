<?php

namespace App\Tests\Smoke;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class KernelSmokeTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(Kernel::class, self::$kernel);
    }
}
