.DEFAULT_GOAL := help

DOCKER_COMPOSE ?= docker compose
PHP := $(DOCKER_COMPOSE) exec -T php

.PHONY: help up install demo test-db test phpstan cs cs-fix check lint-container schema-validate

help: ## Show available targets
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make <target>\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  %-18s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

up: ## Build and start the Docker stack
	$(DOCKER_COMPOSE) up -d --build

install: ## Install Composer deps in the php image
	$(DOCKER_COMPOSE) run --rm --no-deps php composer install --prefer-dist --no-interaction

demo: ## Run migrations, ClickHouse schema and seed demo data (stack must be up)
	$(PHP) bin/console doctrine:migrations:migrate --no-interaction
	$(PHP) bin/console analytics:clickhouse:setup
	$(PHP) bin/console app:demo:seed

test-db: ## Create test database and run migrations
	$(PHP) bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) bin/console doctrine:migrations:migrate --env=test --no-interaction

test: ## Run PHPUnit
	$(PHP) vendor/bin/phpunit

phpstan: ## Run PHPStan
	$(PHP) vendor/bin/phpstan analyse

cs: ## Check coding style (dry-run)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding style
	$(PHP) vendor/bin/php-cs-fixer fix

lint-container: ## Lint Symfony DI container
	$(PHP) bin/console lint:container

schema-validate: ## Validate Doctrine mapping vs DB (test env)
	$(PHP) bin/console doctrine:schema:validate --env=test

check: test phpstan cs lint-container ## Local quality gate (phpunit + phpstan + cs + container)
