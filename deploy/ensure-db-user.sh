#!/bin/sh
# Ensure Laravel DB_USERNAME exists in PostgreSQL (fixes isp vs isp_app mismatch on existing volumes).

DB_USER="${DB_USERNAME:-isp}"
DB_PASS="${DB_PASSWORD:-}"
DB_NAME="${DB_DATABASE:-isp_platform}"
PG_SUPER="${POSTGRES_USER:-isp}"
PG_PASS="${POSTGRES_PASSWORD:-}"
PG_HOST="${DB_HOST:-postgres}"

if [ -z "$DB_PASS" ] || [ -z "$PG_PASS" ]; then
  echo "[entrypoint] Skipping DB user bootstrap (DB_PASSWORD or POSTGRES_PASSWORD not set)"
  exit 0
fi

if [ "$DB_USER" = "$PG_SUPER" ]; then
  echo "[entrypoint] DB_USERNAME matches POSTGRES_USER ($DB_USER)"
  exit 0
fi

if ! command -v psql >/dev/null 2>&1; then
  echo "[entrypoint] psql not installed — rebuild app image (deploy/Dockerfile)"
  exit 0
fi

case "$DB_USER" in
  *[!a-zA-Z0-9_]*)
    echo "[entrypoint] Invalid DB_USERNAME for bootstrap: $DB_USER"
    exit 0
    ;;
esac

echo "[entrypoint] Ensuring PostgreSQL role: $DB_USER (superuser: $PG_SUPER)"

attempt=0
while [ "$attempt" -lt 30 ]; do
  if PGPASSWORD="$PG_PASS" psql -h "$PG_HOST" -U "$PG_SUPER" -d "$DB_NAME" -c '\q' 2>/dev/null; then
    break
  fi
  attempt=$((attempt + 1))
  sleep 2
done

if ! PGPASSWORD="$PG_PASS" psql -h "$PG_HOST" -U "$PG_SUPER" -d "$DB_NAME" -c '\q' 2>/dev/null; then
  echo "[entrypoint] WARNING: PostgreSQL not ready; could not bootstrap DB user"
  exit 0
fi

ESCAPED_PASS=$(printf '%s' "$DB_PASS" | sed "s/'/''/g")

if ! PGPASSWORD="$PG_PASS" psql -h "$PG_HOST" -U "$PG_SUPER" -d "$DB_NAME" -v ON_ERROR_STOP=1 <<-EOSQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$DB_USER') THEN
    CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$ESCAPED_PASS';
  ELSE
    ALTER ROLE $DB_USER WITH PASSWORD '$ESCAPED_PASS';
  END IF;
END
\$\$;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
GRANT ALL ON SCHEMA public TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
EOSQL
then
  echo "[entrypoint] WARNING: could not bootstrap PostgreSQL role $DB_USER"
  exit 0
fi

echo "[entrypoint] PostgreSQL role ready: $DB_USER"
