<?php

namespace App\Tests\Tenant\Application\Handler;

use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\Exception\InvalidTenantNameException;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterTenantHandlerTest extends KernelTestCase
{
    private RegisterTenantHandler $handler;
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

        $entityManager1->createQuery('DELETE FROM App\Tenant\Domain\Entity\Tenant')->execute();

        $this->handler = new RegisterTenantHandler($this->tenants);
    }

    public function testRegistersTenant(): void
    {
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        ($this->handler)(new RegisterTenantCommand($id, 'Casino Alpha'));

        $tenant = $this->tenants->getById(TenantId::fromString($id));
        $this->assertSame('Casino Alpha', $tenant->name());
        $this->assertTrue($tenant->isActive());
    }

    public function testRejectsInvalidId(): void
    {
        $this->expectException(InvalidTenantIdException::class);

        ($this->handler)(new RegisterTenantCommand('not-a-ulid', 'Casino Alpha'));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidTenantNameException::class);

        ($this->handler)(new RegisterTenantCommand('01ARZ3NDEKTSV4RRFFQ69G5FAV', '   '));
    }
}
