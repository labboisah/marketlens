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
	@echo "Platform app is not scaffolded yet. When apps/platform/composer.json exists, run composer install in apps/platform."

desktop-install:
	@echo "Desktop workspace is not scaffolded yet. When package.json exists, run pnpm install from the desktop workspace."

monitoring-install:
	@echo "Monitoring app is not scaffolded yet. When pubspec.yaml exists, run flutter pub get in apps/monitoring-app."

test:
	@echo "No application test suites are scaffolded yet."

lint:
	@echo "No application linters are scaffolded yet."

format:
	@echo "No application formatters are scaffolded yet."

build:
	@echo "No application build targets are scaffolded yet."

clean:
	@echo "No generated application artifacts are scaffolded yet."

docs:
	@echo "Start with README.md, docs/architecture/system-overview.md, and docs/product/development-environment.md."
