<?php

declare(strict_types=1);

// Demo seeder for Manager Library (tasks T-21). Everything goes through the API.
//
//   php seed.php                       40 books, 10 members, 15 loans, 3 of them overdue
//   php seed.php --books=5000          bigger catalog for the search benchmark (T-24)
//
// Meant for an EMPTY database. Overdue loans need the API running with LIBRARY_DEMO_CLOCK=true.

require __DIR__.'/src/ApiClient.php';
require __DIR__.'/src/config.php';

const OVERDUE_LOANS = 3;
const REGULAR_LOANS = 12;
const COPIES_PER_BOOK = 3;

$options = tool_options($argv);
$bookCount = max(40, (int) ($options['books'] ?? 40));
$memberCount = 10;

// Five titles per block; the block decides the category (names from data-model §9)
$blocks = ['Novela', 'Novela', 'Ciencia', 'Tecnología', 'Historia', 'Infantil'];

$titles = [
    ['Cien años de soledad', 'Gabriel García Márquez'], ['El amor en los tiempos del cólera', 'Gabriel García Márquez'],
    ['La vorágine', 'José Eustasio Rivera'], ['María', 'Jorge Isaacs'], ['Rayuela', 'Julio Cortázar'],
    ['Pedro Páramo', 'Juan Rulfo'], ['Ficciones', 'Jorge Luis Borges'], ['Don Quijote de la Mancha', 'Miguel de Cervantes'],
    ['La casa de los espíritus', 'Isabel Allende'], ['El túnel', 'Ernesto Sabato'],
    ['Breve historia del tiempo', 'Stephen Hawking'], ['Cosmos', 'Carl Sagan'], ['El origen de las especies', 'Charles Darwin'],
    ['El gen egoísta', 'Richard Dawkins'], ['Una breve historia de casi todo', 'Bill Bryson'],
    ['Código limpio', 'Robert C. Martin'], ['El programador pragmático', 'Andrew Hunt'], ['Patrones de diseño', 'Erich Gamma'],
    ['Refactorización', 'Martin Fowler'], ['Diseño guiado por el dominio', 'Eric Evans'],
    ['Sapiens', 'Yuval Noah Harari'], ['Historia mínima de Colombia', 'Jorge Orlando Melo'], ['Armas, gérmenes y acero', 'Jared Diamond'],
    ['Las venas abiertas de América Latina', 'Eduardo Galeano'], ['Los pilares de la tierra', 'Ken Follett'],
    ['El principito', 'Antoine de Saint-Exupéry'], ['Matilda', 'Roald Dahl'], ['Donde viven los monstruos', 'Maurice Sendak'],
    ['Charlie y la fábrica de chocolate', 'Roald Dahl'], ['El león, la bruja y el armario', 'C. S. Lewis'],
];

try {
    $api = tool_client();

    $categories = $api->expect(200, 'GET', '/categories')['items'];
    $categoryIdByName = array_column($categories, 'id', 'name');

    echo "Creando $bookCount libros...\n";
    $bookIds = [];

    for ($i = 0; $i < $bookCount; $i++) {
        [$title, $author] = $titles[$i] ?? ['Libro de demostración '.($i + 1), 'Autor '.(($i % 97) + 1)];

        $bookIds[] = $api->expect(201, 'POST', '/books', [
            'title' => $title,
            'author' => $author,
            'isbn' => '978'.str_pad((string) ($i + 1), 10, '0', STR_PAD_LEFT),
            'category_id' => isset($titles[$i])
                ? $categoryIdByName[$blocks[intdiv($i, 5)]]
                : $categories[$i % count($categories)]['id'],
            'total_copies' => COPIES_PER_BOOK,
        ])['id'];
    }

    echo "Creando $memberCount socios...\n";
    $memberIds = [];

    for ($i = 0; $i < $memberCount; $i++) {
        $memberIds[] = $api->expect(201, 'POST', '/members', [
            'full_name' => 'Socio de demostración '.($i + 1),
            'document_number' => (string) (1000000001 + $i),
        ])['id'];
    }

    // Books are handed out in turn over the first 12 titles, so no title runs out of copies
    $nextBook = 0;
    $take = function (int $howMany) use (&$nextBook, $bookIds): array {
        $items = [];

        for ($n = 0; $n < $howMany; $n++) {
            $items[] = ['book_id' => $bookIds[$nextBook % 12]];
            $nextBook++;
        }

        return $items;
    };

    // Overdue loans: lent "20 days ago" through the demo clock, one per member (members 1 to 3)
    echo 'Creando '.OVERDUE_LOANS." préstamos vencidos...\n";
    $twentyDaysAgo = gmdate('Y-m-d\TH:i:s\Z', time() - 20 * 86400);

    for ($i = 0; $i < OVERDUE_LOANS; $i++) {
        $api->expect(201, 'POST', '/loans', [
            'member_id' => $memberIds[$i],
            'items' => $take(1),
        ], ['X-Demo-Now: '.$twentyDaysAgo]);
    }

    // Regular loans for the other seven members; nobody goes over 3 books (RN-2)
    echo 'Creando '.REGULAR_LOANS." préstamos al día...\n";
    $regular = [];

    for ($i = 0; $i < REGULAR_LOANS; $i++) {
        $regular[] = $api->expect(201, 'POST', '/loans', [
            'member_id' => $memberIds[OVERDUE_LOANS + ($i % 7)],
            'items' => $take($i < 5 ? 2 : 1),
        ]);
    }

    // Two loans are returned, so the demo also shows closed loans
    foreach ([5, 6] as $index) {
        $loan = $regular[$index];
        $api->expect(200, 'POST', '/loans/'.$loan['id'].'/items/'.$loan['items'][0]['id'].'/return');
    }

    // Evidence of T-21
    $overdue = count($api->expect(200, 'GET', '/loans/overdue')['items']);
    $today = gmdate('Y-m-d');
    $report = $api->expect(200, 'GET', '/reports/most-borrowed?from='.gmdate('Y-m-d', time() - 30 * 86400).'&to='.$today)['rows'];

    echo "\nResumen\n";
    echo '  Libros:               '.count($bookIds)."\n";
    echo '  Socios:               '.count($memberIds)."\n";
    echo '  Préstamos:            '.(OVERDUE_LOANS + REGULAR_LOANS)."\n";
    echo "  Líneas vencidas:      $overdue\n";
    echo '  Filas en el reporte:  '.count($report)."\n";

    if ($overdue !== OVERDUE_LOANS) {
        fwrite(STDERR, "\nSe esperaban ".OVERDUE_LOANS." líneas vencidas. ¿La API corre con LIBRARY_DEMO_CLOCK=true y la base estaba vacía?\n");
        exit(1);
    }

    if ($report === []) {
        fwrite(STDERR, "\nEl reporte no tiene filas.\n");
        exit(1);
    }

    echo "\nListo.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nError: ".$e->getMessage()."\n");
    exit(1);
}
