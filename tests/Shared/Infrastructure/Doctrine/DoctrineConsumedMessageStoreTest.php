<?php

namespace App\Tests\Shared\Infrastructure\Doctrine;

use App\Shared\Application\Messenger\ConsumedMessageStoreInterface;
use App\Shared\Infrastructure\Doctrine\Entity\ConsumedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineConsumedMessageStoreTest extends KernelTestCase
{
    public function testClaimReturnsTrueOnceThenFalse(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->createQuery('DELETE FROM '.ConsumedMessage::class)->execute();

        $store = self::getContainer()->get(ConsumedMessageStoreInterface::class);
        self::assertInstanceOf(ConsumedMessageStoreInterface::class, $store);

        $this->assertTrue($store->claim('demo_consumer', 'msg-1'));
        $this->assertFalse($store->claim('demo_consumer', 'msg-1'));
        $this->assertTrue($store->claim('demo_consumer', 'msg-2'));
        $this->assertTrue($store->claim('other_consumer', 'msg-1'));
    }
}
