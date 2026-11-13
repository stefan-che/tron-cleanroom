#!/bin/bash
# Creates the encryptor vault (database `encryptor`, user `enc_user`, table `wallet_keys`)
# from the canonical schema ../cleanroom-vault/db.sql, mounted read-only at /vault/db.sql.
#
# The official mysql image runs files from /docker-entrypoint-initdb.d ONLY on the first start
# with an empty data directory. To re-run: stop the stack and remove docker/data/mysql-ks/.
#
# The password comes from DB_PASSWORD_KEY_STORAGE (docker/.env) and replaces CHANGE_ME.
# Works both when sourced by the entrypoint and when executed.

vault_src=/vault/db.sql

if [ ! -f "$vault_src" ]; then
    echo "[vault-init] $vault_src not found. Clone cleanroom-vault next to tron-cleanroom" >&2
    echo "[vault-init] or set ENCRYPTOR_SRC in docker/.env to its path." >&2
    exit 1
fi
if [ -z "${VAULT_DB_PASSWORD:-}" ]; then
    echo "[vault-init] DB_PASSWORD_KEY_STORAGE is empty in docker/.env" >&2
    exit 1
fi
case "$VAULT_DB_PASSWORD" in
    *\'* | *\\*)
        echo "[vault-init] DB_PASSWORD_KEY_STORAGE must not contain ' or \\" >&2
        exit 1
        ;;
esac

vault_pw_sed=$(printf '%s' "$VAULT_DB_PASSWORD" | sed -e 's/[|&]/\\&/g')
sed "s|CHANGE_ME|${vault_pw_sed}|g" "$vault_src" \
    | mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}"
echo "[vault-init] vault schema applied from $vault_src"
