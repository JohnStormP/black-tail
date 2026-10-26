<?php

namespace App\Tests\AI\Infrastructure\Security;

use App\AI\Infrastructure\Security\AiAskAccessVoter;
use App\Identity\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AiAskAccessVoterTest extends TestCase
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

    public function testGrantsAnalystTenantAdminAndSuperAdmin(): void
    {
        $voter = new AiAskAccessVoter();

        foreach (['ROLE_ANALYST', 'ROLE_TENANT_ADMIN', 'ROLE_SUPER_ADMIN'] as $role) {
            $vote = $voter->vote($this->tokenFor($this->userWithRoles($role)), null, [AiAskAccessVoter::AI_ASK]);
            $this->assertSame(VoterInterface::ACCESS_GRANTED, $vote, $role);
        }
    }

    public function testDeniesSupport(): void
    {
        $voter = new AiAskAccessVoter();
        $vote = $voter->vote(
            $this->tokenFor($this->userWithRoles('ROLE_SUPPORT')),
            null,
            [AiAskAccessVoter::AI_ASK],
        );

        $this->assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }
}
