<?php

namespace App\Tests\Shared\Messenger;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class MessengerFailureTransportConfigTest extends KernelTestCase
{
    public function testFailureTransportIsWiredInTestEnv(): void
    {
        self::bootKernel();

        $failed = self::getContainer()->get('messenger.transport.failed');
        self::assertInstanceOf(InMemoryTransport::class, $failed);

        $async = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $async);
    }

    public function testMessengerConfigDeclaresFailureTransportAndRetry(): void
    {
        self::bootKernel();

        self::assertNotNull(self::$kernel);
        $application = new Application(self::$kernel);
        $command = $application->find('debug:config');
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'framework',
            'path' => 'messenger',
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('failure_transport: failed', $output);
        self::assertStringContainsString('max_retries:', $output);
        self::assertStringContainsString("    failed:\n", $output);
    }
}
