# Cómo usar las utilidades

Dos scripts en PHP 8.3, sin dependencias (solo la extensión `curl`). Los dos hablan con la **API**; ninguno toca la base de datos.

## Configuración

Copia `.env.example` a `.env` y completa el usuario y la contraseña del administrador. También puedes pasarlos como variables de entorno.

| Variable | Para qué |
|---|---|
| `API_URL` | Base de la API. Por defecto `http://localhost:8000/api/v1` |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | Cuenta de administrador |

## Sembrador de demostración (T-21)

```bash
php seed.php
```

Crea 40 libros, 10 socios y 15 préstamos, de los cuales 3 quedan vencidos y 2 cerrados. Al final comprueba la evidencia: `GET /loans/overdue` debe devolver 3 líneas y el reporte debe tener filas. Si no se cumple, termina con código 1.

Dos condiciones:

- La base debe estar **vacía**. El sembrador no está pensado para ejecutarse dos veces sobre los mismos datos.
- La API debe correr con `LIBRARY_DEMO_CLOCK=true`. Sin eso no se pueden crear préstamos vencidos por la API, porque el vencimiento siempre es la fecha del préstamo más 14 días.

## Rendimiento de la búsqueda (T-24, RNF-1)

```bash
php seed.php --books=5000
php bench.php
```

`bench.php` hace 20 búsquedas, imprime la mediana y el p95, y termina con código 1 si el p95 es de 300 ms o más. Acepta `--runs=50` y `--q=historia`.

Sembrar 5 000 libros toma unos 20 minutos con `php artisan serve`, porque atiende una petición a la vez.

Medición de referencia (13 de octubre de 2026, `php artisan serve` en Windows, PostgreSQL 16 en Docker, 5 000 libros): mediana 187 ms, p95 203 ms.
