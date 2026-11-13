#!/bin/bash

# No arguments: mysqldump on the host using credentials from .env.
# With --docker <compose dir>: dump inside the app-mysql container (production stack, make backup).

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="$(dirname "$PROJECT_DIR")/backups"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_NAME="db_backup_$TIMESTAMP"

COMPOSE_DIR=""
if [ "$1" = "--docker" ]; then
    COMPOSE_DIR="${2:?docker compose directory is required}"
fi

mkdir -p "$BACKUP_DIR"
cd "$PROJECT_DIR"

echo "Backup started: $(date)"
echo "Backup file: $BACKUP_DIR/$BACKUP_NAME.sql"

if [ -n "$COMPOSE_DIR" ]; then
    echo "Dumping the MySQL database inside the app-mysql container"

    # Single quotes: variables are expanded by the container shell; only it has the root password.
    (cd "$COMPOSE_DIR" && docker compose exec -T app-mysql sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" \
        --no-tablespaces --single-transaction --quick --routines --triggers --events \
        "$MYSQL_DATABASE"') > "$BACKUP_DIR/$BACKUP_NAME.sql"
else
    if [ -f ".env" ]; then
        export $(grep -v '^#' .env | xargs)
    fi

    DB_HOST=${DB_HOST:-127.0.0.1}
    DB_PORT=${DB_PORT:-3306}
    DB_DATABASE=${DB_DATABASE:-laravel}
    DB_USERNAME=${DB_USERNAME:-root}
    DB_PASSWORD=${DB_PASSWORD:-""}

    echo "Dumping the MySQL database: $DB_DATABASE"

    MYSQLDUMP_CMD="mysqldump --host=$DB_HOST --port=$DB_PORT --user=$DB_USERNAME --no-tablespaces --single-transaction"
    if [ -n "$DB_PASSWORD" ]; then
        MYSQLDUMP_CMD="$MYSQLDUMP_CMD --password=$DB_PASSWORD"
    fi
    MYSQLDUMP_CMD="$MYSQLDUMP_CMD $DB_DATABASE"

    $MYSQLDUMP_CMD > "$BACKUP_DIR/$BACKUP_NAME.sql"
fi

if [ $? -ne 0 ]; then
    # Remove the partial dump so rotation never treats it as a valid backup.
    rm -f "$BACKUP_DIR/$BACKUP_NAME.sql"
    echo "Database backup failed!"
    exit 1
fi

echo "Database backup completed"
