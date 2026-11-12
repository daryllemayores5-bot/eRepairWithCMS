<?php

declare(strict_types=1);

final class DemoHttpClient
{
    private CurlHandle $handle;

    private string $responseBody = '';

    private string $origin;

    public function body(): string
    {
        return $this->responseBody;
    }

    public function contentType(): string
    {
        return (string) curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);
    }

    public function __construct(private string $base)
    {
        $origin = getenv('EREPAIR_LIVE_ORIGIN') ?: 'http://127.0.0.1:5173';
        $parts = parse_url($origin);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'http'
            || ! in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Live checks require a root HTTP loopback frontend origin.');
        }
        $this->origin = rtrim($origin, '/');
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
        ]);
    }

    public function request(string $method, string $path, ?array $body = null, int $expected = 200, bool $multipart = false): array
    {
        $headers = ['Accept: application/json', 'Origin: '.$this->origin, 'X-Requested-With: XMLHttpRequest'];
        foreach (curl_getinfo($this->handle, CURLINFO_COOKIELIST) ?: [] as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'XSRF-TOKEN') {
                $headers[] = 'X-XSRF-TOKEN: '.rawurldecode($parts[6]);
            }
        }
        if ($body !== null && ! $multipart) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt($this->handle, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->handle, CURLOPT_HTTPGET, true);
        curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->handle, CURLOPT_URL, $this->base.$path);
        curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $multipart ? $body : json_encode($body, JSON_THROW_ON_ERROR));
        }
        $content = curl_exec($this->handle);
        if ($content === false) {
            throw new RuntimeException('HTTP connection failed: '.curl_error($this->handle));
        }
        $this->responseBody = $content;
        $status = curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE);
        if ($status !== $expected) {
            // Do not print request bodies, cookies, passwords or private records.
            throw new RuntimeException("{$method} {$path}: expected HTTP {$expected}, received {$status}.");
        }
        $type = (string) curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);

        return str_contains($type, 'application/json') && $content !== ''
            ? json_decode($content, true, flags: JSON_THROW_ON_ERROR)
            : [];
    }

    public function login(string $email, string $password): void
    {
        $this->request('GET', '/sanctum/csrf-cookie', expected: 204);
        $this->request('POST', '/api/v1/login', ['email' => $email, 'password' => $password]);
    }
}
