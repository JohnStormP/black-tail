<?php

namespace App\Tests\Tenant\Application\Handler;

use App\Tenant\Application\Command\ActivateTenantCommand;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Command\SuspendTenantCommand;
use App\Tenant\Application\Handler\ActivateTenantHandler;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Application\Handler\SuspendTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\TenantNotFoundException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SuspendAndActivateTenantHandlerTest extends KernelTestCase
{
    private RegisterTenantHandler $register;
    private SuspendTenantHandler $suspend;
    private ActivateTenantHandler $activate;
    private TenantRepositoryInterface $tenants;

    protected function setUp(): void
    {
        self::bootKernel();

        $tenants = self::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $this->tenants = $tenants;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager1 = $entityManager;

        $entityManager1->createQuery('DELETE FROM '.Tenant::class)->execute();

        $this->register = new RegisterTenantHandler($this->tenants);
        $this->suspend = new SuspendTenantHandler($this->tenants);
        $this->activate = new ActivateTenantHandler($this->tenants);
    }

    public function testSuspendThenActivate(): void
    {
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        ($this->register)(new RegisterTenantCommand($id, 'Casino Alpha'));

        ($this->suspend)(new SuspendTenantCommand($id));
        $suspended = $this->tenants->getById(TenantId::fromString($id));
        $this->assertSame(TenantStatus::Suspended, $suspended->status());
        $this->assertFalse($suspended->isActive());

        ($this->activate)(new ActivateTenantCommand($id));
        $active = $this->tenants->getById(TenantId::fromString($id));
        $this->assertSame(TenantStatus::Active, $active->status());
        $this->assertTrue($active->isActive());
    }

    public function testSuspendUnknownTenantFails(): void
    {
        $this->expectException(TenantNotFoundException::class);

        ($this->suspend)(new SuspendTenantCommand('01BX5ZZKBKACTAV9WEVGEMMVRY'));
    }
}
