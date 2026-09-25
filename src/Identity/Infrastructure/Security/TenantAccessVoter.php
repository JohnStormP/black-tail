<?php

namespace App\Identity\Infrastructure\Security;

use App\Tenant\Domain\Entity\Tenant;
use App\Tenant\Domain\Exception\InvalidTenantIdException;
use App\Tenant\Domain\ValueObject\TenantId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class TenantAccessVoter extends Voter
{
    public const string TENANT_ACCESS = 'TENANT_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (self::TENANT_ACCESS !== $attribute) {
            return false;
        }

        return $subject instanceof Tenant
            || $subject instanceof TenantId
            || (is_string($subject) && '' !== $subject);
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

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        try {
            $requestedTenantId = match (true) {
                $subject instanceof Tenant => $subject->id()->toString(),
                $subject instanceof TenantId => $subject->toString(),
                is_string($subject) => TenantId::fromString($subject)->toString(),
                default => null,
            };
        } catch (InvalidTenantIdException) {
            return false;
        }

        if (null === $requestedTenantId) {
            return false;
        }

        if (null === $user->tenantId()) {
            return false;
        }

        return $user->tenantId() === $requestedTenantId;
    }
}
