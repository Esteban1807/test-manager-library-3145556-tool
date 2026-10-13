<?php

declare(strict_types=1);

// Search benchmark (RNF-1, tasks T-24): the book search must answer in under 300 ms.
//
//   php seed.php --books=5000
//   php bench.php                      20 searches, prints the p95 and exits 1 if it is 300 ms or more
//   php bench.php --runs=50 --q=historia

require __DIR__.'/src/ApiClient.php';
require __DIR__.'/src/config.php';

const LIMIT_MS = 300.0;

$options = tool_options($argv);
$runs = max(5, (int) ($options['runs'] ?? 20));
$text = $options['q'] ?? 'volumen';

try {
    $api = tool_client();

    $total = $api->expect(200, 'GET', '/books?size=1')['total'];
    $api->request('GET', '/books?q='.rawurlencode($text)); // warm-up, not measured

    $times = [];

    for ($i = 0; $i < $runs; $i++) {
        $response = $api->request('GET', '/books?q='.rawurlencode($text).'&page=1&size=20');

        if ($response['status'] !== 200) {
            throw new RuntimeException('La búsqueda respondió '.$response['status']);
        }

        $times[] = $response['seconds'] * 1000;
    }

    sort($times);
    $p95 = $times[(int) ceil(0.95 * count($times)) - 1];

    printf("Libros en el catálogo: %d\n", $total);
    printf("Búsquedas medidas:     %d (q=%s)\n", $runs, $text);
    printf("Mediana:               %.1f ms\n", $times[intdiv(count($times), 2)]);
    printf("p95:                   %.1f ms (límite %.0f ms)\n", $p95, LIMIT_MS);

    if ($total < 5000) {
        echo "Aviso: RNF-1 se mide con 5 000 libros; siembra con `php seed.php --books=5000`.\n";
    }

    exit($p95 < LIMIT_MS ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: '.$e->getMessage()."\n");
    exit(1);
}
