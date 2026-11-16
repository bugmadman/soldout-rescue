COMPOSE = docker compose --env-file .env.local
PHP = $(COMPOSE) exec php

.PHONY: init upd updb down in cs fix stan test lint

init: .env.local
	$(COMPOSE) build
	$(COMPOSE) run --rm --no-deps php composer install
	$(COMPOSE) up -d --wait

.env.local:
	printf 'UID=%s\n' "$$(id -u)" > .env.local

upd: .env.local
	$(COMPOSE) up -d

updb: .env.local
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

in:
	$(PHP) bash

cs:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

fix:
	$(PHP) vendor/bin/php-cs-fixer fix

stan:
	$(PHP) vendor/bin/phpstan analyse

test:
	$(PHP) vendor/bin/phpunit

lint: cs stan test
