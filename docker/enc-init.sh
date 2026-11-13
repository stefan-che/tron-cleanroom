#!/usr/bin/env bash
#
# One-time setup of the encryptor secrets for the local docker stack:
#   enc/kek          random KEK, mode 600
#   enc/config.json  from enc/config.json.example: master_key = random master key wrapped
#                    with the KEK; connect3 and connect4 = vault DSN encrypted with the master key
#
# Never overwrites: if enc/config.json already exists, the script exits without changes
# (a new master key would make every stored private key undecryptable).
#
# Needs: enc/encryptor (make enc) and DB_PASSWORD_KEY_STORAGE in docker/.env.
# The vault database itself does not have to be running.
set -euo pipefail

cd "$(dirname "$0")"
ENC_DIR="$(cd ../enc && pwd)"
RUN_IMAGE="${GO_IMAGE:-golang:1.25}"
VAULT_HOST="${VAULT_HOST:-app-mysql-key-storage}"

die() { echo "[enc-init] $*" >&2; exit 1; }

[ -x "$ENC_DIR/encryptor" ] || die "enc/encryptor not found — run: make enc"
[ -f "$ENC_DIR/config.json.example" ] || die "enc/config.json.example not found"

if [ -f "$ENC_DIR/config.json" ]; then
    echo "[enc-init] enc/config.json already exists — left untouched."
    exit 0
fi

[ -f .env ] || die "docker/.env not found — run: make env"
# Last assignment wins, surrounding quotes stripped.
vault_pw="$(sed -n 's/^DB_PASSWORD_KEY_STORAGE=//p' .env | tail -n 1 | sed -e 's/^["'\'']//' -e 's/["'\'']$//')"
[ -n "$vault_pw" ] || die "DB_PASSWORD_KEY_STORAGE is empty in docker/.env"

# The binary is a static linux build for the docker engine's architecture; run it in a
# throwaway container. config.json is read from the working dir, kek from the binary's dir.
encryptor() {
    docker run --rm -v "$ENC_DIR":/enc -w /enc "$RUN_IMAGE" ./encryptor "$@"
}

umask 077
if [ ! -s "$ENC_DIR/kek" ]; then
    head -c 32 /dev/urandom | base64 > "$ENC_DIR/kek"
    echo "[enc-init] created enc/kek"
fi
chmod 600 "$ENC_DIR/kek"

# The binary parses config.json before every command (even wrap_key), so start from the
# example. If anything below fails, remove the half-written file so a re-run starts clean.
cp "$ENC_DIR/config.json.example" "$ENC_DIR/config.json"
trap 'rm -f "$ENC_DIR/config.json" "$ENC_DIR/config.json.tmp"; echo "[enc-init] failed, enc/config.json removed" >&2' ERR

master_key="$(head -c 32 /dev/urandom | base64)"
wrapped="$(encryptor wrap_key "$master_key")"
[ -n "$wrapped" ] || { rm -f "$ENC_DIR/config.json"; die "wrap_key returned nothing"; }

tmp="$ENC_DIR/config.json.tmp"
# base64 never contains '|', so it is a safe sed delimiter.
sed -e "s|\"master_key\": *\"[^\"]*\"|\"master_key\": \"$wrapped\"|" \
    -e "s|\"connect1\": *\"[^\"]*\"|\"connect1\": \"\"|" \
    -e "s|\"connect3\": *\"[^\"]*\"|\"connect3\": \"\"|" \
    "$ENC_DIR/config.json.example" > "$tmp"
mv "$tmp" "$ENC_DIR/config.json"

dsn="enc_user:${vault_pw}@tcp(${VAULT_HOST}:3306)/encryptor"
dsn_enc="$(encryptor encrypt_bd "$dsn")"
[ -n "$dsn_enc" ] || { rm -f "$ENC_DIR/config.json"; die "encrypt_bd returned nothing"; }

# Slot 3 = default (Wallet::CRYPTO_DEFAULT_CONNECT), slot 4 = test wallets (is_test=1).
sed -e "s|\"connect3\": *\"[^\"]*\"|\"connect3\": \"$dsn_enc\"|" \
    -e "s|\"connect4\": *\"[^\"]*\"|\"connect4\": \"$dsn_enc\"|" \
    "$ENC_DIR/config.json" > "$tmp"
mv "$tmp" "$ENC_DIR/config.json"
chmod 600 "$ENC_DIR/config.json"

fingerprint="$(encryptor key_fingerprint)"
trap - ERR

cat <<EOF
[enc-init] enc/config.json written (master_key wrapped with enc/kek; vault DSN in connect3/connect4).

  MASTER KEY (shown once, NOT stored in plaintext anywhere):
      $master_key
  Fingerprint: $fingerprint

  Save the master key OFFLINE (password manager / safe). Losing enc/kek AND this key means
  every wallet private key sealed by this stack is lost. See ../cleanroom-vault/MAINTENANCE.md.
EOF
