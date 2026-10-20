<?php

namespace App\Tests\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ClickHouseClientIntegrationTest extends KernelTestCase
{
    public function testPingAgainstRealClickHouse(): void
    {
        self::bootKernel();

        $client = self::getContainer()->get(ClickHouseClientInterface::class);
        self::assertInstanceOf(ClickHouseClientInterface::class, $client);
        $client->ping();
    }
}
