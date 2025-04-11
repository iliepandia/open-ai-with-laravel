<?php

namespace App\Services\OpenAi;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class OpenAiApi
{
    protected string $baseUrl = 'https://api.openai.com/v1/';

    protected Client $client;

    public function __construct(protected string $apiKey)
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            ''
        ]);
    }

    public function vector_stores_get_file($vectorId, $fileId ) : array
    {
        return self::jsonCall(url: "vector_stores/{{vector_store_id}}/files/{{file_id}}", urlParams: [
            'vector_store_id' => $vectorId,
            'file_id' => $fileId
        ]);
    }

    public function files_get( $fileId ) : array
    {
        return self::jsonCall(url: "files/{{file_id}}", urlParams: [
            'file_id' => $fileId
        ]);
    }

    public function files_get_file_name($fileId) : ?string
    {
        $data = self::files_get($fileId);
        return $data['filename']??null;
    }

    public function assistants_get_vector_store_id($assistantId) : ?string
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

    public function jsonCall(string $method = "GET", string $url = '', array $urlParams = [], ?array $jsonBody = null): array
    {
        $key = md5(json_encode($jsonBody) . json_encode($urlParams) . $url . $method);

        //We will cache the get methods since we don't expect those to change
        if (strtolower($method) == 'get') {
            if (Cache::has($key)) {
                return Cache::get($key);
            }
        }

        $response = $this->call(method: $method, url: $url, urlParams: $urlParams, jsonBody: $jsonBody);
        $string = $response->getBody()->getContents();
        $data = json_decode($string, true);
        if (!$data) {
            Log::error("Could not decode the JSON response: $string");
            throw new \RuntimeException("Could not decode the JSON response.");
        }
        if (strtolower($method) == 'get') {
            Cache::put($key, $data, now()->addHours(24));
        }
        return $data;
    }

    public function call(string $method = "GET", string $url = '', array $urlParams = [], ?array $jsonBody = null): ResponseInterface
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
        return $this->client->request($method, $url, $options);
    }
}
