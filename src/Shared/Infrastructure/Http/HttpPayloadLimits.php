<?php

namespace App\Shared\Infrastructure\Http;

final class HttpPayloadLimits
{
    public const string ULID = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    public const int ULID_LENGTH = 26;

    public const int MAX_AMOUNT_MINOR = 100_000_00;

    public const int IDEMPOTENCY_KEY_MAX = 64;

    public const int NAME_MAX = 255;

    public const int EMAIL_MAX = 255;

    public const int PROVIDER_MAX = 64;

    public const int PROVIDER_REFERENCE_MAX = 128;

    public const int FAILURE_REASON_MAX = 255;

    public const int WINDOW_SECONDS_MAX = 86400;

    /** @var list<string> */
    public const array CURRENCIES = ['USD', 'EUR', 'UAH'];

    /** @var list<string> */
    public const array RISK_RULE_TYPES = [
        'daily_deposit_limit',
        'daily_betting_limit',
        'max_bet',
        'max_withdrawal',
        'bet_velocity',
    ];

    /** @var list<string> */
    public const array RISK_RULE_ACTIONS = ['block', 'flag', 'manual_review'];
}
