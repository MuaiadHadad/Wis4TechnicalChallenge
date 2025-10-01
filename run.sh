#!/usr/bin/env bash
set -euo pipefail

# Change to this script's directory (handles spaces in path)
cd "$(cd "$(dirname "$0")" && pwd)"

# Pick docker compose command
if command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose"
elif docker compose version >/dev/null 2>&1; then
  DC="docker compose"
else
  echo "ERROR: Docker Compose is not installed. Install Docker Desktop or docker-compose." >&2
  exit 1
fi

# Proactive cleanup to avoid name conflicts
echo "[0/4] Cleaning up any previous stack (containers only, keep volumes)..."
set +e
$DC down --remove-orphans 2>/dev/null
# Remove any leftover containers with fixed names (in case they were created outside compose)
for c in wis4_nginx wis4_backend wis4_frontend wis4_mariadb wis4_s3ninja; do
  docker rm -f "$c" >/dev/null 2>&1 || true
done
set -e

echo "[1/4] Building and starting containers..."
$DC up -d --build

DB_CONTAINER=wis4_mariadb
DB_USER=wis4_user
DB_PASS=wis4_password
DB_NAME=wis4_db

# Wait for DB to be ready
echo "[2/4] Waiting for MariaDB to be ready..."
retries=60
sleep_between=2
until docker exec "$DB_CONTAINER" sh -c "mysql -u$DB_USER -p$DB_PASS -e 'SELECT 1' $DB_NAME" >/dev/null 2>&1; do
  retries=$((retries-1))
  if [ $retries -le 0 ]; then
    echo "ERROR: MariaDB did not become ready in time." >&2
    # Show logs for quick debugging
    $DC logs --no-color || true
    exit 1
  fi
  sleep "$sleep_between"
done
echo "MariaDB is ready."

# Check if schema/data needs initialization
echo "[3/4] Checking database schema/data..."
set +e
HAS_USERS_TABLE=$(docker exec "$DB_CONTAINER" sh -c "mysql -N -u$DB_USER -p$DB_PASS -e 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"$DB_NAME\" AND table_name=\"users\";' 2>/dev/null")
set -e

apply_seed=false
if [ "${HAS_USERS_TABLE:-0}" -eq 0 ]; then
  echo "Users table not found. Will apply database/init.sql."
  apply_seed=true
else
  set +e
  USERS_COUNT=$(docker exec "$DB_CONTAINER" sh -c "mysql -N -u$DB_USER -p$DB_PASS -e 'SELECT COUNT(*) FROM $DB_NAME.users;' 2>/dev/null")
  set -e
  if [ "${USERS_COUNT:-0}" -eq 0 ]; then
    echo "Users table is empty. Will apply database/init.sql."
    apply_seed=true
  else
    echo "Database already initialized (users: ${USERS_COUNT}). Skipping seed."
  fi
fi

if [ "$apply_seed" = true ]; then
  echo "[4/4] Applying database/init.sql (schema + demo data)..."
  # Pipe host file into container mysql client
  if [ ! -f database/init.sql ]; then
    echo "ERROR: database/init.sql not found." >&2
    exit 1
  fi
  cat database/init.sql | docker exec -i "$DB_CONTAINER" sh -c "mysql -u$DB_USER -p$DB_PASS $DB_NAME"
  echo "Seed applied."
else
  echo "[4/4] Nothing to seed."
fi

# Final info
cat <<EOF

Stack is up.
- App:           http://localhost
- S3 (S3Ninja):  http://localhost:9000

Demo logins:
- Administrator: admin@wis4.pt / password
- Collaborator:  collaborator1@wis4.pt / password

Tip: To view logs, run: $DC logs --no-color | less
EOF
