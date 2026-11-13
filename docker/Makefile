# Local / single-host stack. Run from tron-cleanroom/docker.
# From scratch: make bootstrap (step-by-step guide: ../docs/QUICKSTART.md).

DC             := docker compose
GO_IMAGE       ?= golang:1.25
# cleanroom-vault checkout; docker-compose.yml reads the same variable from docker/.env.
ENCRYPTOR_SRC  ?= $(or $(shell sed -n 's/^ENCRYPTOR_SRC=//p' .env 2>/dev/null | tail -n 1),../../cleanroom-vault)
SHARED_NETWORK ?= $(or $(shell sed -n 's/^SHARED_NETWORK=//p' .env 2>/dev/null | tail -n 1),processing-net)
# Variables docker-compose.yml cannot start without.
REQUIRED_ENV   := DB_DATABASE DB_USERNAME DB_PASSWORD DB_ROOT_PASSWORD DB_PASSWORD_KEY_STORAGE REDIS_PASSWORD

.DEFAULT_GOAL := init
.PHONY: bootstrap network env check-env enc enc-init enc-check

init: up composer-install migrate seed
up:
	docker compose up -d
composer-install:
	docker compose run --rm app-php composer install
migrate:
	docker compose run --rm app-php php artisan migrate --force
q-restart:
	docker compose run --rm app-php php artisan queue:restart
migrate-down:
	docker compose run --rm app-php php artisan migrate:rollback --force
optimize:
	docker compose run --rm app-php php artisan optimize
view-clear:
	docker compose run --rm app-php php artisan view:clear
seed:
	docker compose run --rm app-php php artisan db:seed
recreate-php:
	docker compose up -d --force-recreate --no-deps --build app-php
restart-php:
	docker compose restart app-php
restart-nginx:
	docker compose restart app-nginx
recreate-mysql:
	docker compose up -d --force-recreate --no-deps --build app-mysql
recreate-mysql-ks:
	docker compose up -d --force-recreate --no-deps --build app-mysql-key-storage
recreate-nginx:
	docker compose up -d --force-recreate --no-deps --build app-nginx
recreate-redis:
	docker compose up -d --force-recreate --no-deps --build app-redis
go-php:
	docker compose exec app-php bash
go-mysql:
	docker compose exec app-mysql bash
go-nginx:
	docker compose exec app-nginx bash
docker-down-clear:
	docker compose down -v --remove-orphans
tests:
	docker compose run app-php php artisan test
docker-fix:
	sh docker-fix.sh

# --- From scratch ------------------------------------------------------------

## Env files, network, encryptor + secrets, containers, deps, APP_KEY, DB, seed, self-check.
## Safe to re-run: nothing that exists is overwritten (env files, enc/kek, enc/config.json, APP_KEY).
bootstrap: env check-env network enc enc-init
	$(DC) up -d --build
	$(DC) exec -T app-php composer install --no-interaction --prefer-dist
	@if grep -Eq '^APP_KEY=.+' ../.env; then echo "APP_KEY already set - not regenerating"; \
		else $(DC) exec -T app-php php artisan key:generate --force; fi
	$(DC) exec -T app-php php artisan migrate --force
	$(DC) exec -T app-php php artisan db:seed --force
	@# queue workers were started before vendor/ existed; restart them
	$(DC) restart app-php
	$(MAKE) enc-check
	@echo
	@echo "Done. Next: create an admin user (../docs/QUICKSTART.md, step 7):"
	@echo "  $(DC) exec app-php php artisan orchid:admin admin admin@example.com <password>"

## Shared docker network for tron-cleanroom <-> cleanroom-watcher (idempotent).
network:
	@docker network inspect $(SHARED_NETWORK) >/dev/null 2>&1 \
		|| docker network create $(SHARED_NETWORK)

## Copy example env files if missing (never overwrites).
env:
	@test -f ../.env || { cp ../.env.example ../.env && echo "created ../.env from ../.env.example"; }
	@test -f .env || { cp .env.example .env && echo "created docker/.env from docker/.env.example"; }

check-env:
	@missing=""; for v in $(REQUIRED_ENV); do \
		grep -Eq "^$$v=.+" .env || missing="$$missing $$v"; \
	done; \
	if [ -n "$$missing" ]; then \
		echo "Fill these in docker/.env, then re-run make bootstrap:$$missing"; exit 1; \
	fi

## Build enc/encryptor from cleanroom-vault: static linux binary for the docker engine's arch.
enc:
	@test -f "$(ENCRYPTOR_SRC)/main.go" || { echo "cleanroom-vault not found at $(ENCRYPTOR_SRC) (set ENCRYPTOR_SRC)"; exit 1; }
	docker run --rm -v "$(abspath $(ENCRYPTOR_SRC))":/src:ro -v "$(abspath ../enc)":/out -w /src \
		-e CGO_ENABLED=0 -e GOFLAGS=-buildvcs=false $(GO_IMAGE) \
		go build -trimpath -ldflags="-s -w" -o /out/encryptor .
	@chmod +x ../enc/encryptor
	@echo "built enc/encryptor"

## Generate enc/kek and enc/config.json (master key + vault DSN). Never overwrites.
enc-init:
	GO_IMAGE=$(GO_IMAGE) bash ./enc-init.sh

## Round trip through the real path: PHP KeyManager -> enc/encryptor -> vault DB.
enc-check:
	$(DC) exec -T app-php php artisan test-encryptor:run --count_test=1
