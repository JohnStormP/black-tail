<?php

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Http\RequestId;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestIdProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return $record;
        }

        $requestId = $request->attributes->get(RequestId::ATTRIBUTE);
        if (!\is_string($requestId) || '' === $requestId) {
            return $record;
        }

        $record->extra['request_id'] = $requestId;

        return $record;
    }
}
