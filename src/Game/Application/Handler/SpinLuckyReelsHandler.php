<?php

namespace App\Game\Application\Handler;

use App\Game\Application\Command\SpinLuckyReelsCommand;
use App\Game\Domain\Entity\GameRound;
use App\Game\Domain\Event\LuckyReelsSpun;
use App\Game\Domain\Exception\GameNotPlayableException;
use App\Game\Domain\Exception\InvalidGameRoundException;
use App\Game\Domain\Provider\GameProviderResolver;
use App\Game\Domain\Repository\GameRepositoryInterface;
use App\Game\Domain\Repository\GameRoundRepositoryInterface;
use App\Game\Domain\Repository\TenantGameRepositoryInterface;
use App\Game\Domain\ValueObject\GameId;
use App\Game\Domain\ValueObject\GameRoundId;
use App\Game\Domain\ValueObject\LuckyReelsConfig;
use App\Player\Domain\Repository\PlayerRepositoryInterface;
use App\Player\Domain\ValueObject\PlayerId;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Domain\Random\RandomIntGeneratorInterface;
use App\Wallet\Domain\Entity\LedgerEntry;
use App\Wallet\Domain\Entity\Wallet;
use App\Wallet\Domain\Exception\LedgerIdempotencyConflictException;
use App\Wallet\Domain\Repository\LedgerEntryRepositoryInterface;
use App\Wallet\Domain\Repository\WalletRepositoryInterface;
use App\Wallet\Domain\ValueObject\Currency;
use App\Wallet\Domain\ValueObject\LedgerEntryId;
use App\Wallet\Domain\ValueObject\LedgerEntryType;
use App\Wallet\Domain\ValueObject\Money;
use App\Wallet\Domain\ValueObject\WalletId;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SpinLuckyReelsHandler
{
    public function __construct(
        private readonly GameRepositoryInterface $games,
        private readonly GameRoundRepositoryInterface $rounds,
        private readonly TenantGameRepositoryInterface $tenantGames,
        private readonly PlayerRepositoryInterface $players,
        private readonly WalletRepositoryInterface $wallets,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly TransactionManagerInterface $transactions,
        private readonly GameProviderResolver $resolver,
        private readonly RandomIntGeneratorInterface $rng,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SpinLuckyReelsCommand $command): void
    {
        $gameRoundId = GameRoundId::fromString($command->roundId);
        $gameId = GameId::fromString($command->gameId);
        $playerId = PlayerId::fromString($command->playerId);
        $walletId = WalletId::fromString($command->walletId);
        $bet = Money::of($command->betMinor, Currency::from($command->currency));
        $reserveEntryId = LedgerEntryId::fromString($command->reserveEntryId);
        $settleEntryId = LedgerEntryId::fromString($command->settleEntryId);
        $payoutEntryId = LedgerEntryId::fromString($command->payoutEntryId);

        $game = $this->games->getById($gameId);
        if (!$game->isActive()) {
            throw GameNotPlayableException::disabled($gameId);
        }

        $player = $this->players->getById($playerId);
        $tenantId = $player->tenantId();
        $link = $this->tenantGames->findByTenantAndGame($tenantId, $gameId);

        if (null === $link) {
            throw GameNotPlayableException::notAssignedToTenant($gameId, $tenantId);
        }

        if (!$link->isEnabled()) {
            throw GameNotPlayableException::disabledForTenant($gameId, $tenantId);
        }

        $provider = $this->resolver->resolve($game->code());

        $spun = null;
        $this->transactions->transactional(function () use (
            $command,
            $gameRoundId,
            $gameId,
            $playerId,
            $tenantId,
            $walletId,
            $bet,
            $reserveEntryId,
            $settleEntryId,
            $payoutEntryId,
            $provider,
            &$spun,
        ): void {
            $wallet = $this->wallets->getByIdForUpdate($walletId);

            if (!$wallet->playerId()->equals($playerId)) {
                throw InvalidGameRoundException::walletPlayerMismatch($gameRoundId, $walletId);
            }

            if (!$wallet->isActive()) {
                throw InvalidGameRoundException::walletNotActive($walletId);
            }

            if ($wallet->currency() !== $bet->currency()) {
                throw InvalidGameRoundException::walletCurrencyMismatch($gameRoundId, $walletId);
            }

            $existing = $this->rounds->findById($gameRoundId);
            if (null !== $existing) {
                if (
                    $existing->gameId()->equals($gameId)
                    && $existing->playerId()->equals($playerId)
                    && $existing->betMinor() === $command->betMinor
                ) {
                    return;
                }

                throw InvalidGameRoundException::roundPayloadConflict($gameRoundId);
            }

            $this->reserveStake($wallet, $reserveEntryId, $bet);

            $config = LuckyReelsConfig::default();
            $result = $provider->spin($this->rng);
            $round = GameRound::record(
                $gameRoundId,
                $gameId,
                $playerId,
                $command->betMinor,
                $config,
                $result,
            );
            $this->rounds->save($round);

            $this->settleStake($wallet, $settleEntryId, $bet);

            if ($round->winMinor() > 0) {
                $this->creditPayout(
                    $wallet,
                    $payoutEntryId,
                    Money::of($round->winMinor(), $bet->currency()),
                );
            }

            $spun = new LuckyReelsSpun(
                $gameRoundId,
                $gameId,
                $playerId,
                $tenantId,
                $bet,
                $round->winMinor(),
            );
        });

        if ($spun instanceof LuckyReelsSpun) {
            $this->eventDispatcher->dispatch($spun);
        }
    }

    private function reserveStake(Wallet $wallet, LedgerEntryId $entryId, Money $stake): void
    {
        if (null !== $this->ledger->findById($entryId)) {
            throw LedgerIdempotencyConflictException::forEntry($entryId);
        }

        $wallet->reserve($stake);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Reserve,
            $stake,
            $wallet->availableBalance(),
        ));
    }

    private function settleStake(Wallet $wallet, LedgerEntryId $entryId, Money $stake): void
    {
        if (null !== $this->ledger->findById($entryId)) {
            throw LedgerIdempotencyConflictException::forEntry($entryId);
        }

        $wallet->settle($stake);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Settle,
            $stake,
            $wallet->availableBalance(),
        ));
    }

    private function creditPayout(Wallet $wallet, LedgerEntryId $entryId, Money $payout): void
    {
        if (null !== $this->ledger->findById($entryId)) {
            throw LedgerIdempotencyConflictException::forEntry($entryId);
        }

        $wallet->credit($payout);
        $this->wallets->save($wallet);
        $this->ledger->save(LedgerEntry::record(
            $entryId,
            $wallet->id(),
            LedgerEntryType::Credit,
            $payout,
            $wallet->availableBalance(),
        ));
    }
}
