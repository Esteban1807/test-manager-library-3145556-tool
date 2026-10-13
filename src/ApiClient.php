<?php

declare(strict_types=1);

// Minimal HTTP client for the Manager Library API. Uses only the curl extension.
// The seeder talks to the API and never to the database (data-model §9, tasks T-21).
final class ApiClient
{
    private \CurlHandle $curl;

    private ?string $token = null;

    public function __construct(private readonly string $baseUrl)
    {
        $this->curl = curl_init();
    }

    public function login(string $username, string $password): void
    {
        $response = $this->request('POST', '/auth/login', ['username' => $username, 'password' => $password]);

        if ($response['status'] !== 200) {
            throw new RuntimeException('No se pudo iniciar sesión: '.($response['body']['message'] ?? 'sin respuesta de la API'));
        }

        $this->token = $response['body']['access_token'];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<int, string> $headers
     * @return array{status: int, body: array<string, mixed>, seconds: float}
     */
    public function request(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $headers[] = 'Accept: application/json';

        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer '.$this->token;
        }

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_reset($this->curl);
        curl_setopt_array($this->curl, [
            CURLOPT_URL => rtrim($this->baseUrl, '/').$path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($this->curl);

        if ($raw === false) {
            throw new RuntimeException('La API no responde en '.$this->baseUrl.': '.curl_error($this->curl));
        }

        return [
            'status' => (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            'body' => json_decode((string) $raw, true) ?? [],
            'seconds' => (float) curl_getinfo($this->curl, CURLINFO_TOTAL_TIME),
        ];
    }

    /**
     * Same as request(), but stops with the API's own message when the status is not the expected one.
     *
     * @param array<string, mixed>|null $body
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    public function expect(int $status, string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $response = $this->request($method, $path, $body, $headers);

        if ($response['status'] !== $status) {
            throw new RuntimeException(sprintf(
                '%s %s respondió %d (%s): %s',
                $method,
                $path,
                $response['status'],
                $response['body']['code'] ?? 'sin código',
                $response['body']['message'] ?? 'sin mensaje',
            ));
        }

        return $response['body'];
    }
}
