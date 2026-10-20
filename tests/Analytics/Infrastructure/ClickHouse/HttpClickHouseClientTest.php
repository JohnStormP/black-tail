<?php

namespace App\Tests\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Domain\Exception\ClickHouseException;
use App\Analytics\Infrastructure\ClickHouse\HttpClickHouseClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClickHouseClientTest extends TestCase
{
    public function testPingSucceedsOnSelectOne(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $body = $options['body'] ?? '';
            $this->assertIsString($body);
            $this->assertStringContainsString('SELECT 1', $body);

            return new MockResponse('Ok.', ['http_code' => 200]);
        });
        $client = $this->client($http);
        $client->ping();
    }

    public function testSelectReturnsDataRows(): void
    {
        $payload = json_encode([
            'data' => [
                ['n' => 1],
            ],
            'rows' => 1,
        ], \JSON_THROW_ON_ERROR);

        $client = $this->client(new MockHttpClient([
            new MockResponse($payload, ['http_code' => 200]),
        ]));
        $result = $client->select('SELECT 1 AS n');
        $this->assertSame(['n' => 1], $result[0]);
    }

    public function testExecuteThrowsOnHttpError(): void
    {
        $client = $this->client(new MockHttpClient([
            new MockResponse('Code: 60. DB::Exception: Table x does not exist', ['http_code' => 404]),
        ]));

        $this->expectException(ClickHouseException::class);
        $client->execute('INSERT INTO x VALUES (1)');
    }

    private function client(MockHttpClient $http): HttpClickHouseClient
    {
        return new HttpClickHouseClient(
            $http,
            'clickhouse',
            8123,
            'blacktail',
            'blacktail',
            'blacktail',
        );
    }
}
