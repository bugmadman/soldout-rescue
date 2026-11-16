COMPOSE = docker compose --env-file .env.local
PHP = $(COMPOSE) exec php

.PHONY: init upd updb down in cs fix stan test lint

init: .env.local
	$(COMPOSE) up -d --build --wait
	@if [ -f composer.json ]; then $(PHP) composer install; fi

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
