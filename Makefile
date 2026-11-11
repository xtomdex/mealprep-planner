.DEFAULT_GOAL := help
.PHONY: help up down wait db-init test cs cs-fix stan deptrac check seed

COMPOSE = docker compose
CONSOLE = php bin/console

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'

up: ## Start PostgreSQL and wait until it accepts connections
	$(COMPOSE) up -d
	@$(MAKE) --no-print-directory wait

down: ## Stop containers (data volume is kept)
	$(COMPOSE) down

wait: ## Block until the database container reports healthy
	@echo "Waiting for PostgreSQL..."
	@until [ "$$(docker inspect --format '{{.State.Health.Status}}' $$($(COMPOSE) ps -q database))" = "healthy" ]; do sleep 1; done
	@echo "PostgreSQL is ready."

db-init: ## Create the dev and test databases and run migrations
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test

test: ## Run the whole test suite
	vendor/bin/phpunit

cs: ## Check coding style (no changes)
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding style
	vendor/bin/php-cs-fixer fix

stan: ## Static analysis (PHPStan level 8)
	$(CONSOLE) cache:warmup --env=dev --quiet
	vendor/bin/phpstan analyse --no-progress

deptrac: ## Check architecture rules between layers and modules
	vendor/bin/deptrac analyse --no-progress

check: cs stan deptrac test ## Everything CI runs

seed: ## Load demo data (arrives with the seed step)
	@echo "Nothing to seed yet."
