# Vault MySQL for the encryptor. The init script is baked in (not bind-mounted) so it keeps
# its exec bit on every host; the schema itself, ../cleanroom-vault/db.sql, is mounted at
# /vault/db.sql by docker-compose.yml.
FROM mysql:8.4
COPY --chmod=755 ./images/mysql-key-storage/10-vault.sh /docker-entrypoint-initdb.d/10-vault.sh
