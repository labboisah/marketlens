.PHONY: help install platform-install desktop-install monitoring-install test lint format build clean docs

help:
	@echo "MarketLens root commands"
	@echo "  make install             - Install all scaffolded app dependencies"
	@echo "  make platform-install    - Install Platform dependencies"
	@echo "  make desktop-install     - Install standalone desktop app dependencies"
	@echo "  make monitoring-install  - Install Monitoring App dependencies"
	@echo "  make test                - Run all configured test suites"
	@echo "  make lint                - Run all configured linters"
	@echo "  make format              - Run all configured formatters"
	@echo "  make build               - Build all scaffolded apps"
	@echo "  make clean               - Remove generated local artifacts"
	@echo "  make docs                - Print documentation entry points"

install: platform-install desktop-install monitoring-install

platform-install:
	composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
	npm install

desktop-install:
	cd ../business-server && npm install
	cd ../business-app && npm install

monitoring-install:
	cd apps/monitoring-app && flutter pub get

test:
	php artisan test
	cd ../business-server && npm test
	cd ../business-app && npm test
	cd apps/monitoring-app && flutter test

lint:
	vendor/bin/phpstan analyse
	cd ../business-server && npm run lint
	cd ../business-app && npm run lint
	cd apps/monitoring-app && flutter analyze

format:
	vendor/bin/pint
	cd ../business-server && npm exec -- prettier . --write
	cd ../business-app && npm exec -- prettier . --write
	cd apps/monitoring-app && dart format .

build:
	npm run build
	cd ../business-server && npm run build
	cd ../business-app && npm run build
	cd apps/monitoring-app && flutter build apk --debug

clean:
	cd ../business-server && npm run clean
	cd ../business-app && npm run clean
	cd apps/monitoring-app && flutter clean

docs:
	@echo "Start with README.md, docs/architecture/system-overview.md, and docs/product/development-environment.md."
