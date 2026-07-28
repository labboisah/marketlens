.PHONY: help install platform-install desktop-install monitoring-install test lint format build clean docs

help:
	@echo "MarketLens root commands"
	@echo "  make install             - Install all scaffolded app dependencies"
	@echo "  make platform-install    - Install Platform dependencies"
	@echo "  make desktop-install     - Install desktop workspace dependencies"
	@echo "  make monitoring-install  - Install Monitoring App dependencies"
	@echo "  make test                - Run all configured test suites"
	@echo "  make lint                - Run all configured linters"
	@echo "  make format              - Run all configured formatters"
	@echo "  make build               - Build all scaffolded apps"
	@echo "  make clean               - Remove generated local artifacts"
	@echo "  make docs                - Print documentation entry points"

install: platform-install desktop-install monitoring-install

platform-install:
	cd apps/platform && composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix

desktop-install:
	@echo "Desktop workspace is not scaffolded yet. When package.json exists, run pnpm install from the desktop workspace."

monitoring-install:
	@echo "Monitoring app is not scaffolded yet. When pubspec.yaml exists, run flutter pub get in apps/monitoring-app."

test:
	cd apps/platform && php artisan test
	@echo "Desktop and Monitoring test suites are not scaffolded yet."

lint:
	cd apps/platform && vendor/bin/phpstan analyse
	@echo "Desktop and Monitoring linters are not scaffolded yet."

format:
	cd apps/platform && vendor/bin/pint
	@echo "Desktop and Monitoring formatters are not scaffolded yet."

build:
	cd apps/platform && pnpm build
	@echo "Desktop and Monitoring build targets are not scaffolded yet."

clean:
	@echo "No generated application artifacts are scaffolded yet."

docs:
	@echo "Start with README.md, docs/architecture/system-overview.md, and docs/product/development-environment.md."
