.PHONY: phpstan help

# Default target
help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  phpstan   Run PHPStan (composer phpstan) inside the app container"
	@echo "  help      Show this help"

# Run PHPStan in the app container
phpstan:
	docker compose run --rm shipmonk-packing-app composer phpstan
