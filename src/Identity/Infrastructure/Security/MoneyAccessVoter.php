<?php

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class MoneyAccessVoter extends Voter
{
    public const string MONEY_ACCESS = 'MONEY_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MONEY_ACCESS === $attribute;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof SecurityUser) {
            return false;
        }

        $roles = $user->getRoles();

        return in_array('ROLE_TENANT_ADMIN', $roles, true)
            || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
