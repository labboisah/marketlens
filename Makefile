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
	cd apps/platform && npm install

desktop-install:
	cd apps/desktop && pnpm install

monitoring-install:
	cd apps/monitoring-app && flutter pub get

test:
	cd apps/platform && php artisan test
	cd apps/desktop && pnpm test
	cd apps/monitoring-app && flutter test

lint:
	cd apps/platform && vendor/bin/phpstan analyse
	cd apps/desktop && pnpm lint
	cd apps/monitoring-app && flutter analyze

format:
	cd apps/platform && vendor/bin/pint
	cd apps/desktop && pnpm format
	cd apps/monitoring-app && dart format .

build:
	cd apps/platform && npm run build
	cd apps/desktop && pnpm build
	cd apps/monitoring-app && flutter build apk --debug

clean:
	cd apps/desktop && pnpm clean
	cd apps/monitoring-app && flutter clean

docs:
	@echo "Start with README.md, docs/architecture/system-overview.md, and docs/product/development-environment.md."
