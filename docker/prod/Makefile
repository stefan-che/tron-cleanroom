# Production stack management. Run all commands from docker/prod.
#
#   make up        — start the stack
#   make deploy    — after git pull: migrations, caches, worker restart, php reload
#   make logs      — tail logs of all services

SHELL := /bin/bash
DC    := docker compose

.DEFAULT_GOAL := help
.PHONY: help build rebuild up down restart ps logs deploy reload migrate optimize clear \
        queue-restart nginx-reload fix-perms composer-install shell shell-root \
        mysql redis backup health versions config scan

help: ## List commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

config: ## Show the resolved compose config (checks variable substitution)
	$(DC) config

build: ## Build images (fast, with layer cache)
	$(DC) build --pull

rebuild: ## Rebuild images from scratch to pull Debian/Alpine security updates
	@# Run this target monthly, not `build`.
	@# `build --pull` refreshes the base image, but the `apt-get upgrade` layer
	@# (or `apk upgrade`) comes from the cache as long as neither the Dockerfile
	@# nor the base image changed, so updates released since the last build never
	@# reach the image. Verified: without --no-cache nginx kept 26 HIGH,
	@# with --no-cache it had zero.
	$(DC) build --pull --no-cache

up: ## Start the stack
	$(DC) up -d

down: ## Stop the stack (data in volumes is kept)
	$(DC) down

restart: ## Restart all services
	$(DC) restart

ps: ## Container status
	$(DC) ps

logs: ## Tail logs
	$(DC) logs -f --tail=200

health: ## Healthcheck status
	@$(DC) ps --format 'table {{.Name}}\t{{.Status}}'

versions: ## Software versions, image build date and OS in running containers
	@# Taken from running containers, not images: shows what is actually running.
	@printf '%-10s %-22s %-10s %-34s %s\n' SERVICE IMAGE BUILT OS VERSION
	@for s in app-nginx app-php app-mysql app-redis; do \
		cid=$$($(DC) ps -q $$s 2>/dev/null); \
		if [ -z "$$cid" ]; then printf '%-10s not running\n' "$$s"; continue; fi; \
		img=$$(docker inspect -f '{{.Config.Image}}' $$cid); \
		built=$$(docker image inspect -f '{{.Created}}' $$(docker inspect -f '{{.Image}}' $$cid) | cut -c1-10); \
		os=$$(docker exec $$cid sh -c '. /etc/os-release && echo "$$PRETTY_NAME"' 2>/dev/null); \
		case $$s in \
			app-nginx) ver=$$(docker exec $$cid nginx -v 2>&1 | sed 's|.*/||') ;; \
			app-php)   ver=$$(docker exec $$cid php -r 'echo PHP_VERSION;') ;; \
			app-mysql) ver=$$(docker exec $$cid mysqld --version | awk '{print $$3}') ;; \
			app-redis) ver=$$(docker exec $$cid redis-server --version | sed 's/.*v=\([^ ]*\).*/\1/') ;; \
		esac; \
		printf '%-10s %-22s %-10s %-34s %s\n' "$$s" "$$img" "$$built" "$$os" "$$ver"; \
	done

# --- Deploy ------------------------------------------------------------------

deploy: ## Apply fresh code: migrations, caches, php-fpm reload, worker restart
	@./deploy.sh

reload: ## Reload code without downtime (opcache reset via graceful fpm reload)
	$(DC) kill -s USR2 app-php
	@# The queue container is not restarted: opcache.enable_cli = 0, so
	@# a new worker process always reads fresh files, and queue:restart
	@# lets the current job finish normally. A container restart would wait
	@# stop_grace_period (620 s) for the same result.
	$(DC) exec -T app-php php artisan queue:restart
	$(DC) restart app-scheduler

migrate: ## Run migrations
	$(DC) exec -T app-php php artisan migrate --force --no-interaction

optimize: ## Rebuild config, route and view caches AND apply them
	@# artisan optimize alone is not enough: caches are php files in bootstrap/cache,
	@# and with opcache.validate_timestamps = 0 php-fpm keeps serving the previous
	@# version from memory. Hence clear + optimize + reload.
	$(DC) exec -T app-php php artisan optimize:clear
	$(DC) exec -T app-php php artisan optimize
	$(MAKE) reload

clear: ## Clear all application caches (and apply the reset)
	$(DC) exec -T app-php php artisan optimize:clear
	$(MAKE) reload

queue-restart: ## Gracefully restart queue workers
	$(DC) exec -T app-php php artisan queue:restart

fix-perms: ## Grant the container write access to vendor, storage and bootstrap/cache
	@# Docker creates missing mount points as root, so
	@# the directories must be handed back to the PUID/PGID user.
	@set -a; . ./.env; set +a; \
	  APP="$${APP_PATH:-../..}"; \
	  mkdir -p "$$APP/vendor" "$$APP/storage" "$$APP/bootstrap/cache"; \
	  echo "chown -R $${PUID:-33}:$${PGID:-33} vendor storage bootstrap/cache"; \
	  sudo chown -R "$${PUID:-33}:$${PGID:-33}" \
	      "$$APP/vendor" "$$APP/storage" "$$APP/bootstrap/cache"
	@echo "Done. Checking:"
	$(DC) exec -T -u www-data app-php sh -c \
	  'for d in vendor storage bootstrap/cache; do \
	     test -w "$$d" && echo "  ok      $$d" || echo "  NO ACCESS $$d"; done'

composer-install: ## composer install inside the container
	$(DC) exec -T -u www-data app-php \
	  composer install --no-dev --no-interaction --prefer-dist \
	    --optimize-autoloader

nginx-reload: ## Reload the nginx config and the php-fpm upstream address
	$(DC) exec -T app-nginx nginx -t && $(DC) exec -T app-nginx nginx -s reload

# --- Access ------------------------------------------------------------------

shell: ## Shell in the php container as www-data
	$(DC) exec -u www-data app-php bash

shell-root: ## Shell in the php container as root
	$(DC) exec app-php bash

mysql: ## MySQL client
	$(DC) exec app-mysql sh -c 'mysql -u root -p"$$MYSQL_ROOT_PASSWORD" $$MYSQL_DATABASE'

redis: ## Redis client
	$(DC) exec app-redis sh -c 'redis-cli -a "$$REDIS_PASSWORD"'

# --- Maintenance -------------------------------------------------------------

backup: ## Dump the main DB to ../../../backups; dumps older than 10 days are removed
	@# Cleanup runs only after a successful dump, otherwise rotation would eat live backups on failures.
	bash ../../db-backup.sh --docker "$(CURDIR)"
	bash ../../db-backup-cleanup.sh

# --- Security ----------------------------------------------------------------

# IMAGE NAMES (tron-api-php:prod) are scanned, not compose service names
# (app-php): trivy knows nothing about the latter and answers
# "no such image". To see what is built: docker images | grep tron-api.
# The vulnerability DB (~120 MB) lives in the trivy-cache volume and is downloaded once, not for
# every image. The volume is shared with the /trivy-scan skill (scan.sh mounts it the same way).
TRIVY := docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
	-v trivy-cache:/root/.cache aquasec/trivy
SCAN_IMAGES := tron-api-nginx:prod tron-api-php:prod tron-api-mysql:prod tron-api-redis:prod

scan: ## Scan images and composer.lock for vulnerabilities (trivy, needs internet)
	@# --ignore-unfixed is mandatory: without it the report fills up with CVEs that
	@# have no fix in the distribution at all (the php image has over two
	@# thousand), and the useful signal drowns in them.
	@for img in $(SCAN_IMAGES); do \
		echo "--- $$img"; \
		$(TRIVY) image --ignore-unfixed --scanners vuln \
			--severity CRITICAL,HIGH "$$img"; \
	done
	@echo "--- composer.lock"
	@docker run --rm -v "$$(cd ../.. && pwd)":/scan:ro -v trivy-cache:/root/.cache \
		aquasec/trivy fs --scanners vuln --severity CRITICAL,HIGH /scan/composer.lock
