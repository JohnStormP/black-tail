.DEFAULT_GOAL := help

DOCKER_COMPOSE ?= docker compose
PHP := $(DOCKER_COMPOSE) exec -T php

.PHONY: help test phpstan cs cs-fix check lint-container schema-validate

help: ## Show available targets
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make <target>\n\n"} /^[a-zA-Z0-9_-]+:.*?##/ { printf "  %-18s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

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
