<?php

namespace App\Tests\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Messenger\LogMessageHandlingMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class LogMessageHandlingMiddlewareTest extends TestCase
{
    public function testDoesNotLogOnDispatchWithoutReceivedStamp(): void
    {
        $logger = $this->logger();
        $middleware = new LogMessageHandlingMiddleware($logger);
        $envelope = new Envelope(new \stdClass());

        $result = $middleware->handle($envelope, $this->stackThatReturns($envelope));

        $this->assertSame($envelope, $result);
        $this->assertSame([], $logger->records);
    }

    public function testLogsReceivedAndHandledWhenReceivedStampPresent(): void
    {
        $logger = $this->logger();
        $middleware = new LogMessageHandlingMiddleware($logger);
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('async')]);

        $result = $middleware->handle($envelope, $this->stackThatReturns($envelope));

        $this->assertSame($envelope, $result);
        $this->assertCount(2, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Messenger message received', $logger->records[0]['message']);
        $this->assertSame(\stdClass::class, $logger->records[0]['context']['message']);
        $this->assertSame('info', $logger->records[1]['level']);
        $this->assertSame('Messenger message handled', $logger->records[1]['message']);
        $this->assertSame(\stdClass::class, $logger->records[1]['context']['message']);
    }

    public function testLogsErrorAndRethrowsWhenHandlerFails(): void
    {
        $logger = $this->logger();
        $middleware = new LogMessageHandlingMiddleware($logger);
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('async')]);
        $failure = new \RuntimeException('boom');

        try {
            $middleware->handle($envelope, $this->stackThatThrows($failure));
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertCount(2, $logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('Messenger message received', $logger->records[0]['message']);
        $this->assertSame('error', $logger->records[1]['level']);
        $this->assertSame('Messenger message failed', $logger->records[1]['message']);
        $this->assertSame(\stdClass::class, $logger->records[1]['context']['message']);
        $this->assertSame('boom', $logger->records[1]['context']['error']);
    }

    private function stackThatReturns(Envelope $envelope): StackInterface
    {
        $next = new class($envelope) implements MiddlewareInterface {
            public function __construct(private readonly Envelope $envelope)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };

        return new class($next) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $next)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };
    }

    private function stackThatThrows(\Throwable $error): StackInterface
    {
        $next = new class($error) implements MiddlewareInterface {
            public function __construct(private readonly \Throwable $error)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw $this->error;
            }
        };

        return new class($next) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $next)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->next;
            }
        };
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: string, message: string, context: array<string, mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (!\is_string($level) && !$level instanceof \Stringable) {
                    throw new \InvalidArgumentException('Log level must be string or Stringable');
                }

                /** @var array<string, mixed> $typedContext */
                $typedContext = $context;

                $this->records[] = [
                    'level' => (string) $level,
                    'message' => (string) $message,
                    'context' => $typedContext,
                ];
            }
        };
    }
}
