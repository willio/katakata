<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use RuntimeException;

final class MetaThreadsApi implements ThreadsApi, ThreadsInsightsApi
{
    public function __construct(
        private readonly string $userId,
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://graph.threads.net/v1.0',
    ) {
        if ($this->userId === '' || $this->accessToken === '') {
            throw new RuntimeException('THREADS_USER_ID and THREADS_ACCESS_TOKEN are required.');
        }
    }

    public function publish(string $text): array
    {
        $container = $this->request('POST', "/{$this->userId}/threads", [
            'media_type' => 'TEXT',
            'text' => $text,
        ]);
        $creationId = $container['id'] ?? null;
        if (!is_string($creationId) || $creationId === '') {
            throw new RuntimeException('Threads did not return a creation id.');
        }

        $published = $this->request('POST', "/{$this->userId}/threads_publish", [
            'creation_id' => $creationId,
        ]);
        $id = $published['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Threads did not return a published media id.');
        }

        return ['id' => $id, 'permalink' => null];
    }

    public function replies(string $mediaId): array
    {
        $payload = $this->request('GET', "/{$mediaId}/replies", [
            'fields' => 'id,text,username,timestamp,permalink,profile_picture_url',
        ]);
        $rows = $payload['data'] ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException('Threads returned an invalid replies response.');
        }

        $replies = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) || !is_string($row['timestamp'] ?? null)) {
                continue;
            }
            $replies[] = [
                'id' => $row['id'],
                'text' => is_string($row['text'] ?? null) ? $row['text'] : '',
                'username' => is_string($row['username'] ?? null) ? $row['username'] : 'Threads user',
                'timestamp' => $row['timestamp'],
                'permalink' => is_string($row['permalink'] ?? null) ? $row['permalink'] : '',
                'avatar_url' => is_string($row['profile_picture_url'] ?? null) ? $row['profile_picture_url'] : null,
            ];
        }

        return $replies;
    }

    public function insights(string $mediaId): array
    {
        $payload = $this->request('GET', "/{$mediaId}/insights", [
            'metric' => 'views,likes,replies,reposts,quotes,shares',
        ]);
        $rows = $payload['data'] ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException('Threads returned an invalid insights response.');
        }

        $metrics = ['views' => 0, 'likes' => 0, 'replies' => 0, 'reposts' => 0, 'quotes' => 0, 'shares' => 0];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || !array_key_exists($row['name'], $metrics)) {
                continue;
            }
            $value = $row['values'][0]['value'] ?? $row['total_value']['value'] ?? 0;
            $metrics[$row['name']] = is_numeric($value) ? max(0, (int) $value) : 0;
        }

        return $metrics;
    }

    /** @param array<string, string> $parameters @return array<string, mixed> */
    private function request(string $method, string $path, array $parameters): array
    {
        $parameters['access_token'] = $this->accessToken;
        $url = rtrim($this->baseUrl, '/') . $path;
        $options = ['http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 15,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        ]];
        if ($method === 'GET') {
            $url .= '?' . http_build_query($parameters);
        } else {
            $options['http']['content'] = http_build_query($parameters);
        }

        $body = @file_get_contents($url, false, stream_context_create($options));
        $status = $http_response_header[0] ?? '';
        if ($body === false || !preg_match('/\s2\d\d\s/', $status)) {
            throw new RuntimeException('Threads API request failed.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Threads returned malformed JSON.', 0, $error);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Threads returned an invalid response.');
        }

        return $decoded;
    }
}
