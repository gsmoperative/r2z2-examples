<?php

namespace R2Z2Examples;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use R2Z2Examples\Filter\FilterPipeline;

class ZKillboardR2Z2
{
    private const BASE_URL = 'https://r2z2.zkillboard.com/ephemeral';
    private const SLEEP_ON_SUCCESS_US = 100_000; // 100ms (~10 req/s, well under 20/s limit)
    private const SLEEP_ON_404_S = 6;
    private const SLEEP_ON_429_S = 2;

    private Client $client;
    private int $lastSequenceId = 0;

    public function __construct(
        private ?string $stateFile = null,
        ?Client $client = null,
        private ?FilterPipeline $filters = null,
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Shade-ZKill-Client/1.0',
            ],
        ]);

        if ($this->stateFile && file_exists($this->stateFile)) {
            $this->lastSequenceId = (int) file_get_contents($this->stateFile);
        }
    }

    /**
     * Fetch the current (latest) sequence ID.
     */
    public function getCurrentSequence(): int
    {
        $data = $this->request('/sequence.json');
        return $data['sequence_id'];
    }

    /**
     * Fetch a single killmail by sequence ID.
     * Returns null on 404 (expired or not yet available).
     */
    public function getKillmail(int $sequenceId): ?array
    {
        return $this->request("/{$sequenceId}.json", allowNotFound: true);
    }

    /**
     * Poll for new killmails continuously. Calls $callback for each killmail
     * that passes the filter pipeline (if configured).
     *
     * @param callable(array $killmail, int $sequenceId): void $callback
     * @param int|null $startFrom Sequence ID to start from (null = resume from state or current)
     */
    public function poll(callable $callback, ?int $startFrom = null): never
    {
        $sequenceId = $startFrom ?? $this->lastSequenceId ?: $this->getCurrentSequence();

        while (true) {
            $killmail = $this->getKillmail($sequenceId);

            if ($killmail === null) {
                sleep(self::SLEEP_ON_404_S);
                continue;
            }

            // Run filter pipeline - skip killmails that don't pass
            if ($this->filters === null || $this->filters->evaluate($killmail)) {
                $callback($killmail, $sequenceId);
            }

            $this->lastSequenceId = $sequenceId;
            $this->saveState();

            $sequenceId++;
            usleep(self::SLEEP_ON_SUCCESS_US);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function request(string $path, bool $allowNotFound = false): ?array
    {
        try {
            $response = $this->client->get($path);
        } catch (ClientException $e) {
            $code = $e->getResponse()->getStatusCode();

            if ($code === 404 && $allowNotFound) {
                return null;
            }

            if ($code === 429) {
                sleep(self::SLEEP_ON_429_S);
                return $this->request($path, $allowNotFound);
            }

            throw new RuntimeException(
                "HTTP {$code} from zKillboard: " . $e->getResponse()->getBody(),
                $code,
                $e,
            );
        } catch (GuzzleException $e) {
            throw new RuntimeException("Request failed: {$e->getMessage()}", 0, $e);
        }

        return json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function saveState(): void
    {
        if ($this->stateFile) {
            file_put_contents($this->stateFile, (string) $this->lastSequenceId);
        }
    }
}
