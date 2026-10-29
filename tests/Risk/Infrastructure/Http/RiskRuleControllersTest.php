<?php

namespace App\Tests\Risk\Infrastructure\Http;

use App\Identity\Application\Command\RegisterUserCommand;
use App\Identity\Application\Handler\RegisterUserHandler;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Risk\Domain\Entity\RiskRule;
use App\Risk\Domain\ValueObject\RiskRuleAction;
use App\Risk\Domain\ValueObject\RiskRuleStatus;
use App\Risk\Domain\ValueObject\RiskRuleType;
use App\Tenant\Application\Command\RegisterTenantCommand;
use App\Tenant\Application\Handler\RegisterTenantHandler;
use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class RiskRuleControllersTest extends WebTestCase
{
    private const string TENANT_A = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string TENANT_B = '01BX5ZZKBKACTAV9WEVGEMMVRY';
    private const string RULE_ID = '01CXYZ00000000000000000001';
    private const string ADMIN_A = '01ARZ3NDEKTSV4RRFFQ69G5F30';
    private const string ADMIN_B = '01ARZ3NDEKTSV4RRFFQ69G5F31';

    private function seed(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $em->createQuery('DELETE FROM '.RiskRule::class)->execute();
        $em->createQuery('DELETE FROM '.User::class)->execute();
        $em->createQuery('DELETE FROM '.Tenant::class)->execute();

        $tenants = static::getContainer()->get(TenantRepositoryInterface::class);
        self::assertInstanceOf(TenantRepositoryInterface::class, $tenants);
        $registerTenant = new RegisterTenantHandler($tenants);
        $registerTenant(new RegisterTenantCommand(self::TENANT_A, 'Casino A'));
        $registerTenant(new RegisterTenantCommand(self::TENANT_B, 'Casino B'));

        $users = static::getContainer()->get(UserRepositoryInterface::class);
        self::assertInstanceOf(UserRepositoryInterface::class, $users);
        $hashers = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        self::assertInstanceOf(PasswordHasherFactoryInterface::class, $hashers);
        $registerUser = new RegisterUserHandler($users, $hashers);
        $registerUser(new RegisterUserCommand(
            self::ADMIN_A,
            'admin-a@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_A,
        ));
        $registerUser(new RegisterUserCommand(
            self::ADMIN_B,
            'admin-b@example.com',
            'secret-password',
            'tenant_admin',
            self::TENANT_B,
        ));
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $client->request(
            'POST',
            '/api/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $email,
                'password' => 'secret-password',
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
    }

    public function testTenantAdminCanCreateGetActivateAndDisableOwnRule(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/risk-rules',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'ruleId' => self::RULE_ID,
                'tenantId' => self::TENANT_A,
                'name' => 'Max bet USD',
                'type' => RiskRuleType::MaxBet->value,
                'threshold' => 10_000,
                'action' => RiskRuleAction::Block->value,
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::RULE_ID, $created['id']);
        $this->assertSame(self::TENANT_A, $created['tenantId']);
        $this->assertSame(RiskRuleStatus::Draft->value, $created['status']);
        $this->assertSame(10_000, $created['threshold']);

        $client->request('GET', '/api/risk-rules/'.self::RULE_ID, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(200);
        $fetched = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('Max bet USD', $fetched['name']);

        $client->request('POST', '/api/risk-rules/'.self::RULE_ID.'/activate', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(200);
        $active = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(RiskRuleStatus::Active->value, $active['status']);

        $client->request('POST', '/api/risk-rules/'.self::RULE_ID.'/disable', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(200);
        $disabled = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(RiskRuleStatus::Disabled->value, $disabled['status']);
    }

    public function testForeignTenantAdminCannotCreateRuleForAnotherTenant(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-b@example.com');

        $client->request(
            'POST',
            '/api/risk-rules',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'ruleId' => self::RULE_ID,
                'tenantId' => self::TENANT_A,
                'name' => 'Max bet USD',
                'type' => RiskRuleType::MaxBet->value,
                'threshold' => 10_000,
                'action' => RiskRuleAction::Block->value,
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedIsDenied(): void
    {
        $client = static::createClient();
        $this->seed();

        $client->request('GET', '/api/risk-rules/'.self::RULE_ID, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testListRequiresTenantIdAndScopesAccess(): void
    {
        $client = static::createClient();
        $this->seed();
        $this->login($client, 'admin-a@example.com');

        $client->request(
            'POST',
            '/api/risk-rules',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'ruleId' => self::RULE_ID,
                'tenantId' => self::TENANT_A,
                'name' => 'Max bet USD',
                'type' => RiskRuleType::MaxBet->value,
                'threshold' => 10_000,
                'action' => RiskRuleAction::Block->value,
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/risk-rules', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(400);

        $client->request(
            'GET',
            '/api/risk-rules?tenantId='.self::TENANT_A,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(200);
        $list = json_decode($client->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($list);
        $ids = array_column($list, 'id');
        self::assertContains(self::RULE_ID, $ids);

        $client->request(
            'GET',
            '/api/risk-rules?tenantId='.self::TENANT_B,
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $this->assertResponseStatusCodeSame(403);
    }
}
