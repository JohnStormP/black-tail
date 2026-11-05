<?php

namespace App\Bonus\Infrastructure\EventListener;

use App\Betting\Domain\Event\BetAccepted;
use App\Bonus\Domain\Event\PlayerBonusCompleted;
use App\Bonus\Domain\Exception\InvalidPlayerBonusException;
use App\Bonus\Domain\Repository\PlayerBonusRepositoryInterface;
use App\Bonus\Domain\ValueObject\PlayerBonusStatus;
use App\Shared\Application\TransactionManagerInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
final class ApplyWageringOnBetAcceptedListener
{
    public function __construct(
        private readonly PlayerBonusRepositoryInterface $playerBonuses,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(BetAccepted $event): void
    {
        $hint = $this->playerBonuses->findOldestActiveByPlayer($event->playerId(), $event->amount()->currency());
        if (null === $hint) {
            return;
        }

        $completedEvent = null;

        $this->transactions->transactional(function () use ($event, $hint, &$completedEvent): void {
            $bonus = $this->playerBonuses->getByIdForUpdate($hint->id());
            if (PlayerBonusStatus::Active !== $bonus->status()) {
                return;
            }

            $bonus->applyWagering($event->amount());

            if (PlayerBonusStatus::Completed === $bonus->status() && $bonus->isCredited()) {
                $walletId = $bonus->walletId();
                if (null === $walletId) {
                    throw InvalidPlayerBonusException::missingCreditedWallet($bonus->id());
                }

                $wallet = $this->wallets->getByIdForUpdate($walletId);
                $money = $bonus->grantedAmount();
                $entryId = LedgerEntryId::fromString((string) new Ulid());
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
