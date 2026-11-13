# enc/ — key encryptor binary

The application stores wallet private keys encrypted with key splitting. Encryption and
decryption are done by a separate Go binary, `encryptor`, that the Laravel app calls from
this directory (`app/Services/Wallet/KeyManager.php` runs `enc/encryptor`).

The binary is **not committed**. Build it and generate the secrets from `docker/`:

```bash
cd docker
make enc        # builds enc/encryptor from ../cleanroom-vault (golang:1.25, static, docker engine arch)
make enc-init   # creates enc/kek and enc/config.json (random master key, vault DSN); never overwrites
make enc-check  # after `make up`: one encrypt/decrypt round trip through the PHP KeyManager
```

`make bootstrap` runs all of them (see `docs/QUICKSTART.md`). Without the Makefile:

```bash
docker run --rm -v "$PWD/../cleanroom-vault":/src:ro -v "$PWD/enc":/out -w /src \
  -e CGO_ENABLED=0 -e GOFLAGS=-buildvcs=false golang:1.25 \
  go build -trimpath -ldflags="-s -w" -o /out/encryptor .
```

Files in this directory:

| File | Committed | Purpose |
|---|---|---|
| `encryptor` | no | the binary built from `../cleanroom-vault` |
| `kek` | no | key-encryption key, `chmod 600` (`make enc-init`; see the [cleanroom-vault README](https://github.com/stefan-che/cleanroom-vault)) |
| `config.json` | no | wrapped master key and encrypted MySQL DSNs; start from `config.json.example` |
| `config.json.example` | yes | template for `config.json` |

How to fill `config.json` (wrap the master key, encrypt DSNs) is described in the
[cleanroom-vault README](https://github.com/stefan-che/cleanroom-vault). The local stack creates the vault in the `app-mysql-key-storage` container from
`../cleanroom-vault/db.sql`; production: `docker/prod/README.md`, section about the `encryptor`
service. The KEK is read only from `kek`; there is no `ENCRYPTOR_KEK` environment variable.
