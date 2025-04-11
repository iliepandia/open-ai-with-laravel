<?php

namespace App\Services\OpenAi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class OpenAiApi
{
    protected string $baseUrl = 'https://api.openai.com/v1/';

    protected Client $client;

    public function __construct(protected string $apiKey = '')
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
        ]);
        if (empty($this->apiKey)) {
            $this->apiKey = config('openai.api_key');
        }
    }

    public function vector_stores_get_file($vectorId, $fileId): array
    {
        return self::jsonCall(url: "vector_stores/{{vector_store_id}}/files/{{file_id}}", urlParams: [
            'vector_store_id' => $vectorId,
            'file_id' => $fileId
        ]);
    }

    public function files_get($fileId): array
    {
        return self::jsonCall(url: "files/{{file_id}}", urlParams: [
            'file_id' => $fileId
        ]);
    }

    public function files_get_file_name($fileId): ?string
    {
        $data = self::files_get($fileId);
        return $data['filename'] ?? null;
    }

    public function assistants_get_vector_store_id($assistantId): ?string
    {
        $assistant = self::assistants_get($assistantId);

        return $assistant["tool_resources"]["file_search"]["vector_store_ids"][0];
    }

    public function assistants_get($assistantId): array
    {
        return $this->jsonCall(url: "assistants/{{assistant_id}}", urlParams: [
            'assistant_id' => $assistantId
        ]);
    }

    public function jsonCall(string $method = "GET", string $url = '', array $urlParams = [],
                             ?array $queryParams = null, ?array $jsonBody = null, bool $cache = true): array
    {
        $key = md5(json_encode($jsonBody) . json_encode($urlParams) . json_encode($queryParams) . $url . $method);

        //We will cache the get methods since we don't expect those to change
        if ($cache && strtolower($method) == 'get') {
            if (Cache::has($key)) {
                return Cache::get($key);
            }
        }

        $response = $this->call(method: $method, url: $url, urlParams: $urlParams, queryParams: $queryParams, jsonBody: $jsonBody);
        $string = $response->getBody()->getContents();
        $data = json_decode($string, true);
        if (!$data) {
            Log::error("Could not decode the JSON response: $string");
            throw new \RuntimeException("Could not decode the JSON response.");
        }
        if ($cache && strtolower($method) == 'get') {
            Cache::put($key, $data, now()->addHours(24));
        }
        return $data;
    }

    public function call(string $method = "GET", string $url = '', array $urlParams = [], ?array $queryParams = null, ?array $jsonBody = null): ResponseInterface
    {
        foreach ($urlParams as $name => $value) {
            $url = str_replace("{{{$name}}}", urlencode($value), $url);
        }
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ],
        ];
        if ($jsonBody) {
            $options['json'] = $jsonBody;
        }
        if($queryParams){
            $options['query'] = $queryParams;
        }
        try {
            return $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            if ($e instanceof ClientException) {
                throw new \RuntimeException("Guzzle Exception: {$e->getResponse()->getBody()->getContents()}", previous: $e);
            }
            throw new \RuntimeException("Guzzle Exception: {$e->getMessage()}", previous: $e);
        }
    }

    public function threads_create_and_run(array $params): array
    {
        return self::jsonCall(
            method: 'POST',
            url: 'threads/runs',
            jsonBody: $params,
        );
    }

    public function threads_runs_create(string $threadId, $params): array
    {
        return self::jsonCall(
            method: 'POST',
            url: 'threads/{{thread_id}}/runs',
            urlParams: ['thread_id' => $threadId],
            jsonBody: $params,
        );
    }

    public function threads_runs_retrieve(string $threadId, string $runId): array
    {
        return self::jsonCall(
            url: 'threads/{{thread_id}}/runs/{{run_id}}',
            urlParams: ['thread_id' => $threadId, 'run_id' => $runId],
            cache: false,  //we don't want to cache this one!
        );
    }

    public function threads_messages_list($threadId, $params) : array
    {
        return self::jsonCall(
            url: 'threads/{{thread_id}}/messages',
            urlParams: ['thread_id' => $threadId],
            queryParams: $params,
            cache: false,
        );
    }
}
