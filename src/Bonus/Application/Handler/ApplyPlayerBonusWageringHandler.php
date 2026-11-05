<?php

namespace App\Bonus\Application\Handler;

use App\Bonus\Application\Command\ApplyPlayerBonusWageringCommand;
use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusId;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ApplyPlayerBonusWageringHandler
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ApplyPlayerBonusWageringCommand $command): void
    {
        $completedEvent = null;

        $this->transactions->transactional(function () use ($command, &$completedEvent): void {
            $playerBonusId = PlayerBonusId::fromString($command->playerBonusId);
            $bonus = $this->playerBonuses->getByIdForUpdate($playerBonusId);
            $amount = Money::of($command->amountMinor, $bonus->grantedAmount()->currency());
            $bonus->applyWagering($amount);

            if (PlayerBonusStatus::Completed === $bonus->status() && $bonus->isCredited()) {
                if (null === $command->unlockEntryId || '' === $command->unlockEntryId) {
                    throw InvalidPlayerBonusException::missingUnlockEntryId($playerBonusId);
                }

                $walletId = $bonus->walletId();
                if (null === $walletId) {
                    throw InvalidPlayerBonusException::missingCreditedWallet($playerBonusId);
                }

                $wallet = $this->wallets->getByIdForUpdate($walletId);
                $money = $bonus->grantedAmount();
                $entryId = LedgerEntryId::fromString($command->unlockEntryId);
                $existing = $this->ledger->findById($entryId);
                if (null !== $existing) {
                    if (
                        !$existing->walletId()->equals($walletId)
                        || LedgerEntryType::BonusUnlock !== $existing->type()
                        || !$existing->amount()->equals($money)
                    ) {
                        throw LedgerIdempotencyConflictException::forEntry($entryId);
                    }
                } else {
                    $wallet->unlockBonus($money);
                    $this->wallets->save($wallet);
                    $this->ledger->save(LedgerEntry::record(
                        $entryId,
                        $wallet->id(),
                        LedgerEntryType::BonusUnlock,
                        $money,
                        $wallet->availableBalance(),
                    ));
                }
            }

            $this->playerBonuses->save($bonus);

            if (PlayerBonusStatus::Completed === $bonus->status()) {
                $completedEvent = new PlayerBonusCompleted(
                    $bonus->id(),
                    $bonus->playerId(),
                    $bonus->campaignId(),
                    $bonus->grantedAmount(),
                );
            }
        });

        if (null !== $completedEvent) {
            $this->eventDispatcher->dispatch($completedEvent);
        }
    }
}
