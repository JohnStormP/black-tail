<?php

namespace App\Tests\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Http\RequestId;
use App\Shared\Infrastructure\Logging\RequestIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestIdProcessorTest extends TestCase
{
    public function testAddsRequestIdFromCurrentRequest(): void
    {
        $request = Request::create('/health');
        $request->attributes->set(RequestId::ATTRIBUTE, 'abc12345');
        $stack = new RequestStack();
        $stack->push($request);

        $processor = new RequestIdProcessor($stack);
        $record = ($processor)($this->record());

        $this->assertSame('abc12345', $record->extra['request_id']);
    }

    public function testLeavesRecordUntouchedWithoutRequest(): void
    {
        $processor = new RequestIdProcessor(new RequestStack());
        $record = ($processor)($this->record());

        $this->assertArrayNotHasKey('request_id', $record->extra);
    }

    private function record(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'hello',
            context: [],
            extra: [],
        );
    }
}
