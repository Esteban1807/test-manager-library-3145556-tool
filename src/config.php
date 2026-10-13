<?php

declare(strict_types=1);

// Reads settings from the environment first and from a local .env file second.
// No secret lives in the repository (constitution Article IX).
function tool_env(string $name, ?string $default = null): ?string
{
    static $file = null;

    if ($file === null) {
        $file = [];
        $path = dirname(__DIR__).'/.env';

        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $file[trim($key)] = trim($value);
            }
        }
    }

    $value = getenv($name);

    if ($value !== false && $value !== '') {
        return $value;
    }

    return $file[$name] ?? $default;
}

// Parses --name=value arguments into ['name' => 'value']
function tool_options(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--([a-z-]+)=(.+)$/', $argument, $match) === 1) {
            $options[$match[1]] = $match[2];
        }
    }

    return $options;
}

function tool_client(): ApiClient
{
    $username = tool_env('ADMIN_USERNAME');
    $password = tool_env('ADMIN_PASSWORD');

    if ($username === null || $password === null) {
        fwrite(STDERR, "Faltan ADMIN_USERNAME y ADMIN_PASSWORD (en el entorno o en .env).\n");
        exit(2);
    }

    $client = new ApiClient(tool_env('API_URL', 'http://localhost:8000/api/v1'));
    $client->login($username, $password);

    return $client;
}
