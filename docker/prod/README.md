# Production stack on Docker

A configuration separate from the local one (`docker/`): its own
`docker-compose.yml`, its own images, its own `.env`. The local stack is not
affected — its files are unchanged.

## Services

| Service         | Purpose                                  | Exposed ports |
|-----------------|------------------------------------------|---------------|
| `app-nginx`     | static files, proxying to php-fpm        | 80            |
| `app-php`       | php-fpm, HTTP requests only              | none          |
| `app-queue`     | supervisor + `queue:work`                | none          |
| `app-scheduler` | `schedule:work` (replaces host cron)     | none          |
| `app-mysql`     | MySQL 8.4 LTS, data in a host volume     | 127.0.0.1:3306 |
| `app-redis`     | Redis 7.2 with password and AOF          | none          |

`backup_db` (the backup key database, hosted on a separate server) stays
external — the stack only connects to it.

## Quick start

```bash
cd docker/prod

# 1. Stack configuration
cp .env.example .env
chmod 600 .env
$EDITOR .env          # domain, passwords, PUID/PGID, limits

# 2. Build and start
make build
make up
make health          # all services must be healthy

# 3. Permissions on the directories the application writes to
make fix-perms

# 4. Dependencies, migrations, caches
./deploy.sh
```

The container listens on **HTTP only, port 80** (`HTTP_PORT` is published
externally): certbot and port 443 have been removed from the configuration, and
TLS is terminated by the front proxy.

Because the site is served over https externally, the scheme has to be passed to
the application explicitly; otherwise it builds form `action` attributes and
redirects with `http://`:

* nginx passes `HTTPS on` and `SERVER_PORT 443` to php-fpm — this makes
  `request()->isSecure()` return true;
* `APP_URL` in the application `.env` must be `https://<domain>` — it makes
  `AppServiceProvider` call `URL::forceScheme('https')`.

Both are required together: the first fixes what the application sees about the
request, the second covers link generation in queues and console commands, where
there is no request.

### PUID / PGID and write permissions

The code is mounted from the host, so the UID inside the container must match
the owner of the files outside — otherwise the application cannot write logs or
cache. The default `33` is `www-data` in Debian.

Three directories are writable: `storage`, `bootstrap/cache` and `vendor`.
**If any of them did not exist on the host, Docker creates the mount point
itself — as root.** In that case composer fails with an unhelpful message:

```
/var/www/app/vendor/dasprid does not exist and could not be created
```

`make fix-perms` creates the missing directories, hands them to the user from
`PUID`/`PGID` and immediately verifies write access from the container. On
startup the containers also warn about this situation — check `make logs`:

```
[entrypoint] WARNING: /var/www/app/vendor is not writable:
owner 0:0, process runs as 33:33. Fix: make fix-perms
```

Take `PUID`/`PGID` from the user who owns the code directory
(`stat -c '%u %g' .`) — then `git pull` on the host and writes from the
container will not conflict.

### composer and platform requirements

`composer install` works without the `--ignore-platform-req*` flags:

- the images use **PHP 8.4** — `composer.lock` contains symfony/* v8, which
  requires php >= 8.4.1;
- packages with an outdated PHP version constraint are vendored in `packages/`
  (`iexbase/web3.php`, `comely-io/data-types`; each `VENDORED.md` records the
  commit, the archive sha256 and the single change to the `php` constraint).

Verify on the built image: `docker compose exec app-php composer check-platform-reqs`.

## Required changes in the application `.env`

This is the Laravel `.env`, not the one next to this README. Inside the docker
network the hosts are different:

```diff
-DB_HOST=127.0.0.1
+DB_HOST=app-mysql
 DB_PORT=3306

-REDIS_HOST=127.0.0.1
+REDIS_HOST=app-redis
 REDIS_PORT=6379
 REDIS_PASSWORD=<same as REDIS_PASSWORD in docker/prod/.env>

 APP_DEBUG=false
-LOG_LEVEL=debug
+LOG_LEVEL=info
```

`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` must match the variables of the same
name in `docker/prod/.env` — the MySQL user is created from them.

Worth considering (optional, but it offloads the main database):

```diff
-CACHE_STORE=database
+CACHE_STORE=redis
-SESSION_DRIVER=database
+SESSION_DRIVER=redis
```

The queue (`QUEUE_CONNECTION=database`) can stay on MySQL: there are few jobs,
and `database` provides a convenient `jobs` table for incident analysis.

## External `encryptor` service and its database

`encryptor` (the `enc/` directory) is a separate Go service that stores parts of
the encryption keys. It is not part of this compose project, but it connects to
the same MySQL, to its own `encryptor` database. The DB schema is
`../cleanroom-vault/db.sql` (the single canonical file; the local stack
`docker/docker-compose.yml` applies it automatically in a separate
`app-mysql-key-storage` container).

**1. Create the database, the account and the table.** The stack starts MySQL
with only the `DB_DATABASE` database and knows nothing about `encryptor`:

```bash
docker compose exec app-mysql mysql -uroot -p <<'SQL'
CREATE DATABASE IF NOT EXISTS encryptor
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'enc_user'@'%'
  IDENTIFIED WITH mysql_native_password BY '<password>';
GRANT SELECT, INSERT ON encryptor.* TO 'enc_user'@'%';
FLUSH PRIVILEGES;

USE encryptor;
CREATE TABLE IF NOT EXISTS wallet_keys (
    salt            varchar(255) not null,
    encrypted       varchar(512) not null,
    key_fingerprint varchar(64)  not null,
    constraint wallet_keys_salt_unique unique (salt)
) collate = utf8mb4_unicode_ci;
CREATE INDEX wallet_keys_encrypted_index       ON wallet_keys (encrypted);
CREATE INDEX wallet_keys_key_fingerprint_index ON wallet_keys (key_fingerprint);
SQL
```

Three points here are not obvious and are worth noting:

* **`@'%'`, not `@'127.0.0.1'`.** The connection reaches MySQL not from loopback
  but from the container address or the docker gateway, so an account bound to
  `127.0.0.1` produces `ERROR 1130 Host is not allowed` even when the network is
  fine. The same applies to a remote database: it needs an entry for the client
  IP.
* **`IDENTIFIED WITH mysql_native_password` explicitly.** Over an unencrypted
  connection the Go driver has trouble with `caching_sha2_password`, the MySQL 8
  default. In 8.4 the native plugin is disabled by default — the stack enables
  it with the `--mysql-native-password=ON` flag in `docker-compose.yml`; without
  the flag such an account gets `ERROR 1524 Plugin not loaded`.
* **Only `SELECT, INSERT`.** The service needs to store and read a key part, but
  not overwrite or delete one: with `DELETE`/`UPDATE`, a compromise turns from a
  leak into a loss of keys. If the service starts using
  `INSERT ... ON DUPLICATE KEY UPDATE`, `UPDATE` will be required — but there is
  no need to grant it in advance.

**2. Set the DSN.** The connection string is encrypted and placed in `connectN`
in `enc/config.json`. The address depends on where the service itself runs:

| Where `encryptor` runs | What to put in the DSN      |
|------------------------|-----------------------------|
| in a container         | `tcp(app-mysql:3306)`       |
| on the host            | `tcp(127.0.0.1:3306)`       |

```
enc_user:<password>@tcp(app-mysql:3306)/encryptor
```

The `encryptor` container must be attached to the `tron-prod_backend` network —
otherwise the name `app-mysql` does not resolve. The host variant additionally
requires a published port (see `ports` of `app-mysql` and the `dbaccess` network
in `docker-compose.yml`); if the service runs in a container, both can be
removed, restoring full isolation of the database.

After changing the config the service must be **restarted**: `config.json` is
read once at startup.

### If writes fail

Run the checks in order, from the database to the service; each one only makes
sense after the previous one:

```bash
# 1. are the account and grants in place?
docker compose exec app-mysql mysql -uroot -p \
  -e "SELECT user, host, plugin FROM mysql.user WHERE user='enc_user';
      SHOW GRANTS FOR 'enc_user'@'%'"

# 2. do connect and write succeed along the same path as the service?
docker run --rm --network tron-prod_backend -e MYSQL_PWD='<password>' tron-api-mysql:prod \
  mysql -h app-mysql -u enc_user encryptor -e "
    SELECT CURRENT_USER(), @@hostname;
    INSERT INTO wallet_keys (salt, encrypted, key_fingerprint)
      VALUES ('__probe__','probe','probe');"
docker compose exec app-mysql mysql -uroot -p encryptor \
  -e "DELETE FROM wallet_keys WHERE salt='__probe__'"

# 3. do the service's attempts reach MySQL?
docker compose exec app-mysql mysql -uroot -p -e "
  SET GLOBAL log_output='TABLE'; SET GLOBAL general_log='ON';"
# ...perform a write operation...
docker compose exec app-mysql mysql -uroot -p -e "
  SELECT event_time, user_host, command_type,
         CONVERT(LEFT(argument,200) USING utf8mb4) AS q
  FROM mysql.general_log
  WHERE user_host LIKE 'enc_user%' OR command_type='Connect'
  ORDER BY event_time DESC LIMIT 40\G"
docker compose exec app-mysql mysql -uroot -p -e "
  SET GLOBAL general_log='OFF'; TRUNCATE mysql.general_log;"
```

Be sure to turn `general_log` off again — it records every query together with
its parameters, and keys pass through this database.

If step 2 succeeds but step 3 shows no attempts from `enc_user`, the database is
not the cause — the service never reaches it. Look at the service next:

* **`127.0.0.1` in the DSN of a containerized service** — the most common cause:
  this is the container's own loopback, and MySQL will never see such attempts.
  Fix it by replacing it with `app-mysql:3306`;
* **the service was not restarted** — compare the process start time with the
  config modification time: `ps -o pid,lstart,cmd -C encryptor` and
  `stat -c '%y %n' config.json`;
* **the wrong `config.json` is being read** — the path is relative, so it depends
  on the working directory: `sudo ls -l /proc/$(pgrep -x encryptor)/cwd`;
* **the DSN decrypts to garbage** — if `encrypt_bd` encrypted with one key while
  the service starts with a different `kek`, the driver fails while parsing the
  string, before any connection is made. Verify with the paired decrypt command:
  it must return the original string character for character.

`deploy.sh` does not touch `encryptor` at all — it deploys only Laravel.

## Deployment

```bash
cd /path/to/code && git pull
cd docker/prod && ./deploy.sh     # or make deploy
```

This is the only command needed after `git pull` or an `.env` change — it is
self-contained. `deploy.sh` steps: write-permission check →
`docker compose up -d` (apply compose changes) → `composer install
--no-dev` → migrations → seeders → cache reset and rebuild → graceful php-fpm
reload → `queue:restart` and scheduler restart → nginx reload.

**Why the application `.env` used to not be picked up.** The php services
intentionally have no `env_file`. Docker reads such a file only when the
container is created and injects the values into the process environment, while
Laravel loads `.env` via `Dotenv::createImmutable` — an existing environment
variable wins over the file and is never overwritten by it. While `env_file` was
attached, edits to `.env` had no effect until the container was recreated: the
application served values "that had long been gone from the file". Now the code
is mounted and `.env` is read directly, so it is enough to rebuild the config
cache — which `deploy.sh` does (or `make optimize`, if only the cache is
needed).

**Why an explicit reload is needed.** The image sets
`opcache.validate_timestamps=0`: PHP does not check file timestamps and avoids
extra stat calls on every request — a noticeable gain on a bind mount. The
trade-off is that changes are not picked up by themselves after `git pull`.
`deploy.sh` sends php-fpm the `USR2` signal (a soft worker reload that does not
interrupt in-flight requests) and restarts the containers with long-lived
processes.

If you want code to be picked up automatically, set
`PHP_OPCACHE_VALIDATE_TIMESTAMPS=1` in `docker/prod/.env` and recreate the
containers. Changes then apply within `opcache.revalidate_freq` (2 s), at the
cost of a small performance drop. Workers and the scheduler still have to be
restarted — the process keeps bytecode in memory.

Rebuilding the images (`make build`) is needed only when the Dockerfiles or the
list of PHP extensions change.

**Never run `php artisan optimize` manually in the container.** The config and
route caches are regular php files in `bootstrap/cache`, and with
`opcache.validate_timestamps = 0` php-fpm keeps serving the previous version
from memory: the console shows new values, the web shows old ones. The most
visible symptom is a `RouteNotFoundException` for a route that exists in the
code, or a webhook sent to the old `APP_URL`. Use `make optimize` — it runs
`optimize:clear` (deleting, not overwriting), then `optimize`, then a graceful
php-fpm reload and `queue:restart`.

## TLS and front proxy

The stack itself does not terminate TLS — HAProxy in front of it does, and
traffic reaches us over HTTP. Certbot has been removed from the image (git
history has it if it needs to be restored).

On the HAProxy side the backend needs no `ssl` and must pass headers through:

```haproxy
server app <host-ip>:<HTTP_PORT> check
option forwardfor
http-request set-header X-Forwarded-Proto https
option httpchk GET /healthz
http-check expect status 200
```

`/healthz` returns 200 for any `Host`, so it is suitable for the health check.

The stack does two things, and both are required — the site is https externally
while the container sees http, and without them Laravel builds links with the
`http` scheme: form `action` attributes, redirects, `asset()` (the latter breaks
the admin panel and Log Viewer — the browser blocks mixed content and the js
does not load).

1. nginx passes `HTTPS on` and `SERVER_PORT 443` to php-fpm — this makes
   `request()->isSecure()` return true (see `templates/app.conf.template`);
2. `APP_URL` in the application `.env` must be `https://<domain>` — it makes
   `AppServiceProvider` call `URL::forceScheme('https')`. This also works where
   there is no HTTP request at all: queues, artisan commands, emails.

Separately, add the HAProxy subnet to `images/nginx/geo/trusted-proxies.inc` —
otherwise `X-Forwarded-For` from it is discarded just like spoofed values, the
API IP whitelist sees the proxy address instead of the client, and the rate
limit puts all clients into one bucket.

### If the site does not open

Check in order where the path breaks:

```bash
docker compose ps                      # nginx healthy, port published
ss -tlnp | grep -E ':(80|8080)\s'      # does the host listen on the port configured in HAProxy
curl -s -o /dev/null -w '%{http_code}\n' -H "Host: <domain>" http://<host-ip>:<port>/
sudo tcpdump -ni eth0 'tcp port <port>' -c 30   # someone should be opening
                                                # the site at this moment
```

If `curl` against the internal address returns 200 but `tcpdump` is silent,
nothing reaches the server and HAProxy needs fixing (wrong backend address/port,
backend DOWN, a firewall between networks). Note that your own `curl` to the host
address goes through loopback and will not show up in `tcpdump` on `eth0`.

## Maintenance

```bash
make ps                # status
make optimize          # rebuild caches AND apply them (clear + optimize + reload)
make clear             # clear caches and apply the reset
make reload            # reload code without rebuilding caches
make health            # healthchecks
make logs              # tail logs of all services
make shell             # bash in the php container as www-data
make backup            # dump the main DB to ./backups
make scan              # scan images and composer.lock for vulnerabilities
make nginx-reload      # after recreating app-php: re-read the upstream address
```

DB backup in the host cron:

```cron
0 3 * * * cd /path/to/code/docker/prod && make backup >> /var/log/db-backup.log 2>&1
```

Images and dependencies are scanned for vulnerabilities with `make scan`.

## Security measures

**Network.** Only nginx port 80 is exposed. MySQL and Redis live in the
`backend` network with `internal: true` — it has no route to the internet and
publishes no ports to the host. In the local configuration `3306`, `3307`, `5432`
and `6380` are published on all interfaces; in production that would expose the
database.

**Passwords.** `MYSQL_ROOT_PASSWORD` is not hardcoded in compose but comes from
`.env` with `chmod 600`. The application has a separate MySQL user; root is for
maintenance only.

**Read-only code.** `/var/www/app` is mounted `:ro`; only `storage`,
`bootstrap/cache` and `vendor` (the last one for `composer install`) are
writable. The web process cannot rewrite its own code, which prevents the
typical persistence scenario after a web shell upload.

**Only `index.php` is executed.** All other `.php` files in `public/` get 403. A
forgotten debug script or an uploaded file will not be executed.

**Client IP spoofing protection.** The application determines the IP as
`request()->server('HTTP_CF_CONNECTING_IP', request()->ip())`
(`App\Helpers\Development::getRealIpUser`), and the whitelist in
`AccessApiIpMiddleware` relies on that IP. So that a client cannot supply this
header itself, nginx overwrites `CF-Connecting-IP` and `X-Forwarded-For` with
`$client_ip`: the header from a trusted proxy subnet (the list is
`images/nginx/geo/trusted-proxies.inc`, empty by default) or the real
`$remote_addr`. Verified with a request carrying forged headers — the real
address reaches PHP.

**Request body is not logged.** In the local `nginx.conf` the `main` format
contains `body="$request_body"`. Private keys, provider API keys and AML data
pass through this API, so the production format omits the field.

**Rate limiting.** 10 requests per minute on the admin login form (password
guessing protection), 30 r/s on `/api/` and `/telegram/` with burst, and a limit
on concurrent connections per address.

**Headers.** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`. HSTS is not set — over http it is meaningless and would
only break access.

**TLS.** The stack is designed for a front proxy that terminates TLS. Keep the
proxy → stack segment on an internal network.

**nginx base image — alpine.** The Debian variant pulled in libxml2, libxslt,
libgd, libtiff, gnutls, krb5 and certbot; after switching to alpine and removing
certbot, trivy reports no findings for the image.

**Foreign Host.** A request with an unknown `Host` is closed at the connection
level (`return 444`) and never reaches the application.

**PHP.** `expose_php=Off`, `display_errors=Off`. For the web context (the FPM
pool only, not CLI), `exec`, `passthru`, `shell_exec`, `system`, `popen` and
`pcntl_exec` are disabled and `open_basedir` is set. `proc_open` is left enabled
intentionally: Symfony Process uses it, and the scheduler runs commands through
it.

**MySQL.** `local_infile=0`, `secure_file_priv`, `skip_name_resolve`, symbolic
links disabled.

**Redis.** A password is required; `FLUSHALL`, `FLUSHDB`, `CONFIG` and
`SHUTDOWN` are disabled by renaming.

**Process isolation.** Web, queues and scheduler are separate containers with
their own limits. A memory leak in a worker does not take down HTTP request
handling, and a web restart does not interrupt running jobs.

### Host-level and application-level recommendations

These are handled at the host and code level rather than in the Docker
configuration:

* **Host firewall.** Docker writes rules to iptables bypassing ufw. Restrict
  ports in the `DOCKER-USER` chain, otherwise `ufw deny` has no effect.
* **Application logs.** Review what is written to `storage/logs` and set the
  logging level according to your policy.
* **`storage/logs` rotation.** Container logs are rotated (json-file, 10 MB × 5),
  while Laravel files are not. Set up logrotate on the host or use
  `LOG_CHANNEL=daily`.
* **Secrets in `.env`.** Compose reads them in plain text. For stricter handling,
  use docker secrets or an external secret store.
* **`DASHBOARD_PREFIX`** hides the admin panel but does not replace 2FA and an
  IP whitelist.

## Performance measures

* **OPcache** with 256 MB, 20,000 files and `validate_timestamps=0` — the local
  image has no opcache configured at all.
* **realpath cache** of 4 MB for 10 minutes: stat calls are expensive on a bind
  mount.
* **Separate containers** for web, queues and scheduler — the processes do not
  compete for the same CPU limits.
* **Scheduler included in the stack.** The local compose had none:
  `schedule:run` lived in the host cron. Now `app-scheduler` is part of the stack,
  and the schedule from `App\Console\Handler` works right after `make up`.
* **Scheduled worker restarts** (`--max-jobs`, `--max-time`,
  `pm.max_requests`) — protection against memory leaks in long-running processes.
* **nginx**: gzip, `sendfile`/`tcp_nopush`, keepalive to php-fpm, 30-day static
  cache, FastCGI buffers, `worker_connections 4096`.
* **MySQL**: `innodb_buffer_pool_size=1G`, `O_DIRECT`, binlog for PITR, slow log
  with a 2 s threshold.
* **Redis**: AOF + RDB, `volatile-lru` — only cache entries with a TTL are
  evicted, while Telegram bot dialog states (keys without TTL) stay in place.
* **CPU and memory limits** on every service: one service cannot consume the
  whole machine.
* **Docker log rotation** (10 MB × 5 per container) — without it json-file grows
  until the disk is full.
* **Lighter PHP image**: `imagick` and `decimal` were removed — `app/` does not
  use them, and the build took minutes and pulled in ~200 MB of dependencies.
  Without imagick, `pragmarx/google2fa-qrcode` switches to `SvgImageBackEnd` on
  its own, and the 2FA QR code keeps working. If the extensions are needed after
  all, `images/php/Dockerfile` contains a ready commented-out block.

## Verified

Both images build; `nginx -t` passes; the whole stack is up (mysql, redis, php,
nginx, queue, scheduler). Verified on a live stack:

* the application is served over http (Laravel reaches its own code and renders
  a page; the 500 in the smoke run is caused by an empty test DB, not by
  configuration);
* `/healthz` → 200, static files → 200;
* foreign `Host` → connection closed (444);
* 403 on `/.env`, `/.git/config`, `/storage/logs/*`, `/fpm-status`, `/fpm-ping`
  and on any `.php` other than `index.php`;
* php-fpm status is available from inside the container via `cgi-fcgi`;
* workers are started by supervisor as `www-data`, logs go to `docker logs`;
* trivy: 0 fixable vulnerabilities in both built images.

Not yet verified against live data: migrations and queue operation with a real
database.
