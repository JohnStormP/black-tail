<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\CreateBonusCampaignCommand;
use App\Bonus\Domain\Entity\BonusCampaign;
use App\Bonus\Domain\Repository\BonusCampaignRepositoryInterface;
use App\Bonus\Domain\ValueObject\BonusCampaignId;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\Money;

final class CreateBonusCampaignHandler
{
    public function __construct(
        private readonly BonusCampaignRepositoryInterface $campaigns,
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function __invoke(CreateBonusCampaignCommand $command): void
    {
        $id = BonusCampaignId::fromString($command->campaignId);
        $tenantId = TenantId::fromString($command->tenantId);
        $this->tenants->getById($tenantId);
        $amount = Money::of($command->bonusAmountMinor, Currency::from($command->currency));
        $campaign = BonusCampaign::create($id, $tenantId, $command->name, $amount, $command->wageringMultiplier);
        $this->campaigns->save($campaign);
    }
}
