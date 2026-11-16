#!/usr/bin/env bash
# Web container (ROLE=web, default): wait for the DB, create JWT keys, migrate, seed demo data.
# Worker container (ROLE=worker): starts directly — the web container owns one-time setup.
set -euo pipefail
cd /app

if [ "${ROLE:-web}" = "web" ]; then
  echo "[zyntara] waiting for the database…"
  for _ in $(seq 1 60); do
    if php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; then break; fi
    sleep 1
  done

  # Keys live on a shared volume so every replica verifies the same tokens.
  php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
  php bin/console messenger:setup-transports --no-interaction

  if [ "${SEED_DEMO:-0}" = "1" ]; then
    php bin/console app:seed-demo --no-interaction
  fi
  chown -R www-data:www-data var config/jwt /data/storage
fi

exec "$@"
