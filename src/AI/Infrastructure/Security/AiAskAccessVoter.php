<?php

namespace App\AI\Infrastructure\Security;

use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class AiAskAccessVoter extends Voter
{
    public const string AI_ASK = 'AI_ASK';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::AI_ASK === $attribute;
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

        return in_array('ROLE_ANALYST', $roles, true)
            || in_array('ROLE_TENANT_ADMIN', $roles, true)
            || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
