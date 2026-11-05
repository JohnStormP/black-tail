<?php

namespace App\Shared\Infrastructure\Demo;

final class DemoSeedIds
{
    public const string TENANT_ID = '01JC9K2M5N7P8Q3R4S6T7V8W9X';
    public const string ADMIN_USER_ID = '01JC9K2M5N7P8Q3R4S6T7V8WAX';
    public const string SUPER_ADMIN_USER_ID = '01JC9K2M5N7P8Q3R4S6T7V8WEX';
    public const string PLAYER_ID = '01JC9K2M5N7P8Q3R4S6T7V8WBX';
    public const string WALLET_ID = '01JC9K2M5N7P8Q3R4S6T7V8WCX';
    public const string DEPOSIT_LEDGER_ID = '01JC9K2M5N7P8Q3R4S6T7V8WDX';
    public const string LUCKY_REELS_GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WFX';
    public const string CRASH_GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WGX';
    public const string LUCKY_REELS_TENANT_GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WHX';
    public const string CRASH_TENANT_GAME_ID = '01JC9K2M5N7P8Q3R4S6T7V8WJX';

    public const string TENANT_NAME = 'Black Tail Demo';
    public const string ADMIN_EMAIL = 'admin@blacktail.demo';
    public const string ADMIN_PASSWORD = 'secret-password';
    public const string ADMIN_ROLE = 'tenant_admin';
    public const string SUPER_ADMIN_EMAIL = 'super@blacktail.demo';
    public const string SUPER_ADMIN_PASSWORD = 'secret-password';
    public const string SUPER_ADMIN_ROLE = 'super_admin';
    public const string PLAYER_EMAIL = 'player@blacktail.demo';
    public const string WALLET_CURRENCY = 'USD';
    public const int DEPOSIT_AMOUNT = 10_000;
    public const string LUCKY_REELS_CODE = 'lucky_reels';
    public const string LUCKY_REELS_NAME = 'Lucky Reels';
    public const string CRASH_CODE = 'crash';
    public const string CRASH_NAME = 'Crash';

    private function __construct()
    {
    }
}
