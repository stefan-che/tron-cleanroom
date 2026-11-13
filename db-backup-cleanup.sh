#!/bin/bash

# Deletes database backups older than DAYS_TO_KEEP (10) days.
# Run separately from db-backup.sh, e.g. from cron:
# 0 4 * * * /var/www/app/db-backup-cleanup.sh >> /var/log/backup-cleanup.log 2>&1

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="$(dirname "$PROJECT_DIR")/backups"
DAYS_TO_KEEP=10

if [ ! -d "$BACKUP_DIR" ]; then
    echo "Backup directory not found: $BACKUP_DIR"
    exit 1
fi

echo "Removing backups older than $DAYS_TO_KEEP days in $BACKUP_DIR: $(date)"

FIND_EXPR=(-maxdepth 1 -type f \( -name "db_backup_*.sql" -o -name "db_backup_*.zip" \) -mtime "+$DAYS_TO_KEEP")

DELETED_COUNT=0
FREED_BYTES=0
while IFS= read -r -d '' FILE; do
    FREED_BYTES=$((FREED_BYTES + $(stat -c%s "$FILE" 2>/dev/null || stat -f%z "$FILE")))
    DELETED_COUNT=$((DELETED_COUNT + 1))
    rm -f "$FILE"
done < <(find "$BACKUP_DIR" "${FIND_EXPR[@]}" -print0)

FREED_HUMAN=$(numfmt --to=iec --suffix=B "$FREED_BYTES" 2>/dev/null || echo "$FREED_BYTES bytes")

echo "Files deleted: $DELETED_COUNT"
echo "Space freed: $FREED_HUMAN"
echo "Cleanup finished: $(date)"
