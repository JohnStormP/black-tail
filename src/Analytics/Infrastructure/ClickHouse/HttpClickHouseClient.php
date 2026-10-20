<?php

namespace App\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Application\ClickHouse\ClickHouseClientInterface;
use App\Analytics\Domain\Exception\ClickHouseException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(ClickHouseClientInterface::class, public: true)]
final class HttpClickHouseClient implements ClickHouseClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(CLICKHOUSE_HOST)%')]
        private readonly string $host,
        #[Autowire('%env(int:CLICKHOUSE_PORT)%')]
        private readonly int $port,
        #[Autowire('%env(CLICKHOUSE_DB)%')]
        private readonly string $database,
        #[Autowire('%env(CLICKHOUSE_USER)%')]
        private readonly string $user,
        #[Autowire('%env(CLICKHOUSE_PASSWORD)%')]
        private readonly string $password,
    ) {
    }

    public function ping(): void
    {
        $this->execute('SELECT 1');
    }

    public function execute(string $sql): void
    {
        $this->request($sql);
    }

    public function select(string $sql): array
    {
        $body = $this->request($sql.' FORMAT JSON');
        $response = json_decode($body, true);

        if (!\is_array($response) || !\array_key_exists('data', $response) || !\is_array($response['data']) || !array_is_list($response['data'])) {
            throw ClickHouseException::invalidResponse('expected JSON object with data array');
        }

        $rows = [];
        foreach ($response['data'] as $row) {
            if (!\is_array($row)) {
                throw ClickHouseException::invalidResponse('expected each data row to be an object');
            }

            $normalized = [];
            foreach ($row as $key => $value) {
                if (!\is_string($key)) {
                    throw ClickHouseException::invalidResponse('expected each data row to be an object');
                }
                $normalized[$key] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function request(string $sql): string
    {
        $url = sprintf('http://%s:%d/', $this->host, $this->port);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'query' => [
                    'database' => $this->database,
                ],
                'headers' => [
                    'X-ClickHouse-User' => $this->user,
                    'X-ClickHouse-Key' => $this->password,
                ],
                'body' => $sql,
                'timeout' => 5,
            ]);

            $status = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($status >= 400) {
                throw ClickHouseException::queryFailed('' !== trim($content) ? trim($content) : 'HTTP '.$status);
            }

            return $content;
        } catch (TransportExceptionInterface $e) {
            throw ClickHouseException::unreachable($e->getMessage());
        }
    }
}
