<?php

namespace App\Payment\Domain\ValueObject;

enum PaymentWebhookOutcome: string
{
    case Completed = 'completed';
    case Failed = 'failed';
}
