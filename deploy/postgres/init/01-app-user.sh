#!/bin/bash
# Runs once when the postgres data volume is first created.
set -e

APP_USER="${APP_DB_USERNAME:-}"
APP_PASS="${APP_DB_PASSWORD:-}"

if [ -z "$APP_USER" ] || [ "$APP_USER" = "$POSTGRES_USER" ]; then
  echo "postgres-init: app user same as POSTGRES_USER, nothing to create"
  exit 0
fi

if [ -z "$APP_PASS" ]; then
  echo "postgres-init: APP_DB_PASSWORD not set, skipping app user creation"
  exit 0
fi

ESCAPED_PASS="${APP_PASS//\'/\'\'}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$APP_USER') THEN
    CREATE ROLE $APP_USER WITH LOGIN PASSWORD '$ESCAPED_PASS';
  END IF;
END
\$\$;
GRANT ALL PRIVILEGES ON DATABASE $POSTGRES_DB TO $APP_USER;
GRANT ALL ON SCHEMA public TO $APP_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $APP_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $APP_USER;
EOSQL

echo "postgres-init: ensured role $APP_USER"
