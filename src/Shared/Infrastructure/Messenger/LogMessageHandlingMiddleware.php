<?php

namespace App\Shared\Infrastructure\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class LogMessageHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $messageClass = $envelope->getMessage()::class;
        $received = null !== $envelope->last(ReceivedStamp::class);

        if ($received) {
            $this->logger->info('Messenger message received', [
                'message' => $messageClass,
            ]);
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            if ($received) {
                $this->logger->error('Messenger message failed', [
                    'message' => $messageClass,
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
        if ($received) {
            $this->logger->info('Messenger message handled', [
                'message' => $messageClass,
            ]);
        }

        return $envelope;
    }
}
