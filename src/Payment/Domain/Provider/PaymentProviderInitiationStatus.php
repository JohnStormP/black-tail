<?php

namespace App\Payment\Domain\Provider;

enum PaymentProviderInitiationStatus: string
{
    case Submitted = 'submitted';
    case Declined = 'declined';
}
