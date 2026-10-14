#!/bin/sh
set -eu
# Las migraciones son una acción explícita de despliegue, no se ejecutan aquí.
php artisan library:bootstrap-admin --no-interaction
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
