#!/bin/sh
set -e

cd /app

if [ ! -d vendor ]; then
	echo "[entrypoint] Instaluję zależności Composera..."
	composer install --no-interaction --prefer-dist
fi

echo "[entrypoint] Czekam na bazę danych..."
i=0
until php bin/console dbal:run-sql 'SELECT 1' >/dev/null 2>&1; do
	i=$((i + 1))
	if [ "$i" -ge 30 ]; then
		echo "[entrypoint] Baza nie odpowiedziała po 30 próbach — przerywam." >&2
		exit 1
	fi
	sleep 1
done

echo "[entrypoint] Migracje..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "${LOAD_FIXTURES:-0}" = "1" ]; then
	echo "[entrypoint] Dane przykładowe (tylko gdy tabela book jest pusta)..."
	php bin/console app:load-fixtures-if-empty
fi

exec "$@"
