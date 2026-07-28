# Development Tooling and Installation Guide

This guide defines the shared development tooling for the MarketLens repository.
It covers Windows and Ubuntu setup for the four-application architecture:
Platform, Shop App, Client App, and Monitoring App.

Do not commit real environment files, passwords, Firebase credentials, API keys,
private keys, production tokens, customer data, or local database files. Commit
only `.env.example` templates with safe placeholder values.

## Supported Development Hosts

- Windows 11 with PowerShell, Windows Terminal, Git for Windows, Docker Desktop,
  and WSL 2 enabled where possible.
- Ubuntu LTS with Docker Engine, Docker Compose plugin, and standard build
  packages.

Windows developers may use native Windows tooling or WSL 2. Use one environment
consistently per application to avoid path and native-module rebuild issues.

## Version Guidance

- Node.js: use the active LTS line in `.nvmrc`. This repository currently pins
  Node.js `24`.
- pnpm: install through Corepack unless an app later pins a package-manager
  version in `package.json`.
- PHP: use a supported PHP version compatible with the current Laravel stable
  release. Laravel 12 requires PHP `>= 8.2`.
- Java: use JDK 17 for Flutter Android development.
- Flutter: use the stable channel.
- PostgreSQL and Redis: use versions matching local Docker Compose services or
  the deployment baseline once infrastructure is scaffolded.

Reference documentation:

- Node.js releases: <https://nodejs.org/en/about/previous-releases>
- Laravel deployment requirements: <https://laravel.com/docs/12.x/deployment>
- Flutter Android tooling notes: <https://flutter.dev/docs/get-started/install>
- Electron release schedule: <https://releases.electronjs.org/schedule>

## General Tools

### Git

Windows:

```powershell
winget install --id Git.Git -e
git --version
```

Ubuntu:

```bash
sudo apt update
sudo apt install -y git
git --version
```

Recommended settings:

```bash
git config --global init.defaultBranch main
git config --global pull.rebase false
git config --global core.autocrlf input
```

On Windows, `core.autocrlf input` works well with this repository because
`.editorconfig` enforces LF line endings.

### Git LFS

Use Git LFS only for large binary assets that genuinely belong in source
control. Do not use it for secrets, customer data, local databases, production
exports, or generated build artifacts.

Windows:

```powershell
winget install --id GitHub.GitLFS -e
git lfs install
git lfs version
```

Ubuntu:

```bash
sudo apt install -y git-lfs
git lfs install
git lfs version
```

### Docker

Docker is used for local infrastructure such as PostgreSQL, Redis, and future
service dependencies.

Windows:

- Install Docker Desktop.
- Enable WSL 2 integration.
- Start Docker Desktop before running local infrastructure.

```powershell
winget install --id Docker.DockerDesktop -e
docker version
docker compose version
```

Ubuntu:

Install Docker Engine and the Compose plugin from Docker's official packages
when possible:

```bash
docker version
docker compose version
```

If Docker commands require `sudo`, either use `sudo docker ...` or add your user
to the `docker` group according to your workstation policy.

### Make

The repository includes a root `Makefile` as a shared command index.

Ubuntu:

```bash
sudo apt install -y make
make help
```

Windows:

- Use the documented PowerShell commands when `make` is not installed.
- If your team standardizes on `make` for Windows, install it through WSL, MSYS2,
  Scoop, Chocolatey, or another approved workstation package source.

### Node.js LTS and pnpm

Install Node.js using a version manager.

Windows with nvm-windows:

```powershell
winget install --id CoreyButler.NVMforWindows -e
nvm install 24
nvm use 24
node --version
corepack enable
corepack prepare pnpm@latest --activate
pnpm --version
```

Ubuntu with nvm:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
nvm install
nvm use
node --version
corepack enable
corepack prepare pnpm@latest --activate
pnpm --version
```

Use `nvm install` from the repository root so `.nvmrc` selects the expected
Node.js line.

### PHP and Composer

Platform development requires PHP, Composer, and Laravel-compatible PHP
extensions.

Windows options:

- Use Laragon, Herd, XAMPP, or manually installed PHP.
- Ensure `php.exe` and `composer` are available in `PATH`.
- Enable required extensions in `php.ini`.

Ubuntu:

```bash
sudo apt update
sudo apt install -y php-cli php-fpm php-curl php-mbstring php-xml php-zip \
  php-pgsql php-sqlite3 php-redis php-bcmath php-intl unzip
php --version
composer --version
```

Composer on Windows:

```powershell
winget install --id Composer.Composer -e
composer --version
```

Composer on Ubuntu should be installed from Composer's official installer or
your organization's approved package source.

### PostgreSQL Client Tools

Install client tools even when the database itself runs in Docker.

Windows:

```powershell
winget install --id PostgreSQL.PostgreSQL -e
psql --version
```

Ubuntu:

```bash
sudo apt install -y postgresql-client
psql --version
```

### Redis Client Tools

Windows:

- Prefer Docker or WSL for Redis.
- Use `redis-cli` from WSL, Docker, or a trusted Redis-compatible package.

Ubuntu:

```bash
sudo apt install -y redis-tools
redis-cli --version
```

### Flutter SDK, Android Studio, Android SDK, and JDK

Install Flutter stable and Android Studio for Monitoring App development.

Windows:

```powershell
winget install --id Google.AndroidStudio -e
winget install --id EclipseAdoptium.Temurin.17.JDK -e
flutter --version
flutter doctor
```

Ubuntu:

```bash
sudo apt install -y clang cmake ninja-build pkg-config libgtk-3-dev
java -version
flutter --version
flutter doctor
```

Android Studio setup:

- Install the Android SDK.
- Install Android SDK Platform Tools.
- Install an Android platform API level supported by the current Flutter stable
  release.
- Create at least one Android Virtual Device for emulator testing.
- Accept Android licenses:

```bash
flutter doctor --android-licenses
```

### Visual Studio Code

Windows:

```powershell
winget install --id Microsoft.VisualStudioCode -e
code --version
```

Ubuntu:

Install VS Code from Microsoft's official package repository or approved
workstation image, then verify:

```bash
code --version
```

Recommended extensions:

- EditorConfig.EditorConfig
- GitHub.vscode-pull-request-github
- eamodio.gitlens
- ms-azuretools.vscode-docker
- bmewburn.vscode-intelephense-client
- xdebug.php-debug
- bradlc.vscode-tailwindcss
- dbaeumer.vscode-eslint
- esbenp.prettier-vscode
- vitest.explorer
- ms-playwright.playwright
- Dart-Code.dart-code
- Dart-Code.flutter
- redhat.vscode-yaml

## Platform Tools

The Platform is the cloud Laravel application in `apps/platform`.

Required local tools:

- PHP with Laravel-required extensions.
- Composer.
- PostgreSQL.
- Redis.
- Node.js and pnpm for Vite assets.
- Docker Compose for local infrastructure.
- Laravel CLI where useful.

Laravel-required PHP extensions include:

- Ctype
- cURL
- DOM
- Fileinfo
- Filter
- Hash
- Mbstring
- OpenSSL
- PCRE
- PDO
- Session
- Tokenizer
- XML

MarketLens Platform development also expects:

- `pdo_pgsql` for PostgreSQL.
- `redis` or a supported Redis client package for queues, cache, Horizon, and
  Reverb-related workflows.
- `bcmath` and `intl` where money, localization, or framework packages require
  them.
- `zip` for Composer package extraction.

When the Platform is scaffolded:

```bash
cd apps/platform
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate
pnpm dev
```

Install the Laravel CLI only as a convenience:

```bash
composer global require laravel/installer
laravel --version
```

## Desktop Tools

The desktop workspace contains the Shop App, Client App, and shared packages
under `apps/desktop`.

Required local tools:

- Node.js LTS.
- pnpm.
- Electron.
- Electron Forge.
- Native build tools for `better-sqlite3`.
- Python for `node-gyp`.

Windows native build tools:

```powershell
winget install --id Microsoft.VisualStudio.2022.BuildTools -e
winget install --id Python.Python.3.12 -e
python --version
```

In the Visual Studio Build Tools installer, include C++ build tools and the
Windows SDK. If `node-gyp` cannot find Python, configure it:

```powershell
npm config set python python
```

Ubuntu native build tools:

```bash
sudo apt install -y build-essential python3 make g++ pkg-config libsecret-1-dev
python3 --version
```

When the desktop workspace is scaffolded:

```bash
cd apps/desktop
pnpm install
pnpm --filter shop-app dev
pnpm --filter client-app dev
```

Use Electron Forge through package scripts instead of installing project-local
tooling globally.

## Monitoring Tools

The Monitoring App is the Android-first Flutter app in `apps/monitoring-app`.

Required local tools:

- Flutter SDK on the stable channel.
- Dart SDK from Flutter.
- JDK 17.
- Android Studio.
- Android SDK and Platform Tools.
- Android emulator.
- Firebase CLI where Firebase Cloud Messaging, Crashlytics, or project setup
  workflows require it.

Firebase CLI:

```bash
npm install -g firebase-tools
firebase --version
```

Do not commit Firebase service-account files, `google-services.json`,
`GoogleService-Info.plist`, Crashlytics upload tokens, or production Firebase
credentials unless the security policy explicitly approves a sanitized
development fixture.

When the Monitoring App is scaffolded:

```bash
cd apps/monitoring-app
flutter pub get
flutter doctor
flutter test
flutter run
```

## Root Tasks

The root `Makefile` exposes repository-level commands:

```bash
make install
make platform-install
make desktop-install
make monitoring-install
make test
make lint
make format
make build
make clean
```

At the moment, application scaffolding is intentionally not implemented. These
targets are placeholders that document the expected workflow and will be wired
to Composer, pnpm, Flutter, Pest, Vitest, Playwright, Pint, Larastan, and Flutter
Test when the owning apps are created.

Windows developers without `make` can run the underlying commands documented in
the application sections. If repository-level automation needs richer
cross-platform behavior later, add a minimal root `package.json` or task runner
only after the app package manifests exist.

## Environment Files

Copy example files to local ignored files:

```bash
cp apps/platform/.env.example apps/platform/.env
cp apps/desktop/apps/shop-app/.env.example apps/desktop/apps/shop-app/.env
cp apps/desktop/apps/client-app/.env.example apps/desktop/apps/client-app/.env
cp apps/monitoring-app/.env.example apps/monitoring-app/.env
```

PowerShell equivalent:

```powershell
Copy-Item apps/platform/.env.example apps/platform/.env
Copy-Item apps/desktop/apps/shop-app/.env.example apps/desktop/apps/shop-app/.env
Copy-Item apps/desktop/apps/client-app/.env.example apps/desktop/apps/client-app/.env
Copy-Item apps/monitoring-app/.env.example apps/monitoring-app/.env
```

Development values:

- May use localhost, local ports, local database names, and generated throwaway
  app keys.
- Must not be reused for production.
- May enable debug logging.

Test values:

- Should use isolated databases, queues, cache stores, ports, and filesystem
  paths.
- Must be safe to delete.
- Must not point to production, staging, or a real shop device.

Production secrets:

- Belong in the deployment platform, secret manager, CI secret store, or mobile
  distribution pipeline.
- Must never be committed to Git, screenshots, issue comments, sample exports,
  or documentation.
- Include database passwords, app keys, JWT secrets, encryption keys, API tokens,
  Firebase credentials, S3 credentials, webhook secrets, and signing keys.

Local Shop App configuration:

- Owns the local SQLite path.
- Owns the local API bind host and port.
- Owns LAN access controls and allowed client origins.
- Owns synchronization settings used to reach the Platform.

Cloud Platform configuration:

- Owns PostgreSQL, Redis, queues, mail, S3-compatible storage, Reverb, Sanctum,
  Horizon, and synchronization ingress.
- Owns tenant-aware cloud APIs and monitoring API endpoints.

Client App server-discovery configuration:

- Points to a Shop App local API on LAN or Wi-Fi.
- May use manual host and port values during development.
- Must never bypass the Shop App local API or read SQLite directly.

Monitoring API configuration:

- Points only to the Platform API.
- Must never point to a Shop App.
- Uses synchronized Platform data for reporting and owner visibility.

## Troubleshooting

### PHP Extension Errors

- Run `php -m` and confirm required extensions are loaded.
- Check the active `php.ini` with `php --ini`.
- On Windows, make sure CLI PHP and web PHP are the same installation.
- On Ubuntu, install missing packages such as `php-curl`, `php-mbstring`,
  `php-xml`, `php-pgsql`, `php-redis`, `php-bcmath`, `php-intl`, or `php-zip`.

### Composer Errors

- Run `composer diagnose`.
- Confirm the expected PHP version with `php --version`.
- Delete only generated dependency folders such as `vendor/` when reinstalling.
- Avoid committing `vendor/`.

### PostgreSQL Connection Failures

- Confirm the server or Docker container is running.
- Check host, port, database, username, and password in `.env`.
- Test with `psql -h 127.0.0.1 -p 5432 -U marketlens`.
- On Windows, verify Docker Desktop is running if PostgreSQL is containerized.

### Redis Failures

- Confirm Redis is running.
- Test with `redis-cli -h 127.0.0.1 -p 6379 ping`.
- Check queue, cache, broadcast, and Horizon environment values.
- On Windows, prefer Docker or WSL for Redis during development.

### Electron Native-Module Rebuild Failures

- Confirm Node.js matches `.nvmrc`.
- Reinstall dependencies after switching Node versions.
- Run Electron rebuild through the package script once it exists.
- Verify Python and native C++ build tools are discoverable by `node-gyp`.

### better-sqlite3 Build Failures

- Confirm Python, `make`, and C++ compilers are installed.
- On Windows, install Visual Studio Build Tools with C++ workload and Windows
  SDK.
- On Ubuntu, install `build-essential python3 make g++`.
- Remove generated `node_modules/` and reinstall after changing Node.js or
  Electron versions.

### Flutter Doctor Failures

- Run `flutter doctor -v`.
- Confirm Flutter is on the stable channel.
- Confirm JDK 17 is first in `PATH` or configured in Android Studio.
- Accept Android licenses with `flutter doctor --android-licenses`.

### Android SDK Problems

- Open Android Studio SDK Manager.
- Install Android SDK Platform Tools.
- Install an Android API level supported by the current Flutter stable release.
- Create or repair an Android Virtual Device in Device Manager.
- Confirm `adb devices` lists the emulator or attached device.

### Port Conflicts

- Platform HTTP, Vite, Redis, PostgreSQL, Shop App local API, Socket.IO, Reverb,
  and emulator ports may conflict with existing processes.
- Change only local development ports in ignored `.env` files.
- On Windows, inspect ports with `netstat -ano`.
- On Ubuntu, inspect ports with `ss -ltnp`.

### Windows Firewall Blocking Client App Connections

- Confirm the Shop App local API binds to the intended LAN interface.
- Allow the Shop App executable or development server through Windows Defender
  Firewall for private networks.
- Verify the Client App can reach `http://SHOP_HOST:SHOP_PORT/health` or the
  eventual health endpoint.
- Keep firewall access restricted to trusted shop networks.
