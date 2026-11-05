<?php

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\MoneyAccessVoter;
use App\Identity\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class MoneyAccessVoterTest extends TestCase
{
    private function tokenFor(SecurityUser $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function userWithRoles(string ...$roles): SecurityUser
    {
        return new SecurityUser(
            '01ARZ3NDEKTSV4RRFFQ69G5F30',
            'user@example.com',
            'hash',
            array_values($roles),
            true,
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        );
    }

    public function testGrantsTenantAdminAndSuperAdmin(): void
    {
        $voter = new MoneyAccessVoter();

        foreach (['ROLE_TENANT_ADMIN', 'ROLE_SUPER_ADMIN'] as $role) {
            $vote = $voter->vote(
                $this->tokenFor($this->userWithRoles($role)),
                null,
                [MoneyAccessVoter::MONEY_ACCESS],
            );
            $this->assertSame(VoterInterface::ACCESS_GRANTED, $vote, $role);
        }
    }

    public function testDeniesSupportAndAnalyst(): void
    {
        $voter = new MoneyAccessVoter();

        foreach (['ROLE_SUPPORT', 'ROLE_ANALYST'] as $role) {
            $vote = $voter->vote(
                $this->tokenFor($this->userWithRoles($role)),
                null,
                [MoneyAccessVoter::MONEY_ACCESS],
            );
            $this->assertSame(VoterInterface::ACCESS_DENIED, $vote, $role);
        }
    }
}
