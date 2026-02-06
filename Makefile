.PHONY: phpstan phpcs phpcbf phpcbf-all help

# Default target
help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  phpstan     Run PHPStan (composer phpstan) inside the app container"
	@echo "  phpcs       Run PHP CodeSniffer (composer phpcs) inside the app container"
	@echo "  phpcbf      Run PHP CodeSniffer fixer once (composer phpcbf)"
	@echo "  help        Show this help"

# Run PHPStan in the app container
phpstan:
	docker compose run --rm shipmonk-packing-app composer phpstan

# Run PHP CodeSniffer in the app container
phpcs:
	docker compose run --rm shipmonk-packing-app composer phpcs

# Run PHP CodeSniffer fixer once in the app container
phpcbf:
	docker compose run --rm shipmonk-packing-app composer phpcbf

