# Builds development-signed beta installers without changing persistent signing settings.
[CmdletBinding()]
param(
    [ValidateSet('server', 'app', 'both')][string]$Application = 'both',
    [string]$ServerPath = 'C:\laragon\www\marketlens-business-server',
    [string]$AppPath = 'C:\laragon\www\marketlens-business-app'
)
$ErrorActionPreference = 'Stop'
$names = @('WINDOWS_CERTIFICATE_FILE','WINDOWS_CERTIFICATE_PASSWORD','WINDOWS_PUBLISHER_THUMBPRINTS','BUSINESS_UPDATE_CHANNEL','MARKETLENS_UPDATE_CHANNEL','MARKETLENS_ALLOW_UNSIGNED_DEVELOPMENT')
$previous = @{}
foreach ($name in $names) { $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
    . (Join-Path $PSScriptRoot 'use-development-signing.ps1')
    $logDirectory = Join-Path $env:LOCALAPPDATA 'MarketLens\DevelopmentSigning'
    $targets = @()
    if ($Application -in @('server','both')) { $targets += @{Key='server';Path=$ServerPath;Package='business-server'} }
    if ($Application -in @('app','both')) { $targets += @{Key='app';Path=$AppPath;Package='business-app'} }
    foreach ($target in $targets) {
        $project = (Resolve-Path -LiteralPath $target.Path).Path
        $package = Get-Content -LiteralPath (Join-Path $project 'package.json') -Raw | ConvertFrom-Json
        if ($package.name -ne $target.Package) { throw 'Unexpected desktop project.' }
        $log = Join-Path $logDirectory ($target.Key + '-build-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
        Write-Output ('Building development beta: ' + $package.name + ' ' + $package.version)
        Push-Location $project
        try {
            foreach ($stage in @('test:readiness', 'typecheck', 'make')) {
                Write-Output ('Stage: ' + $stage)
                $arguments = @('run', $stage)
                if ($stage -eq 'test:readiness' -and $target.Key -eq 'server') { $arguments += @('--', '--maxWorkers=1') }
                # Native tools can write normal progress to stderr. Redact before logging.
                $ErrorActionPreference = 'Continue'
                & npm.cmd @arguments 2>&1 | ForEach-Object {
                    $line = $_.ToString().Replace($env:WINDOWS_CERTIFICATE_PASSWORD, '[REDACTED]')
                    Add-Content -LiteralPath $log -Value $line
                    Write-Output $line
                }
                $code = $LASTEXITCODE
                $ErrorActionPreference = 'Stop'
                if ($code -ne 0) { throw ($target.Key + ' ' + $stage + ' failed. Review the redacted private build log.') }
            }
            $installerName = if ($target.Key -eq 'server') { 'MarketLensBusinessServerSetup-beta.exe' } else { 'MarketLensClientAppSetup-beta.exe' }
            $installer = Join-Path $project ('out\make\squirrel.windows\x64\' + $installerName)
            $signature = Get-AuthenticodeSignature -LiteralPath $installer
            if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Thumbprint -ne $env:WINDOWS_PUBLISHER_THUMBPRINTS) {
                throw ('Installer signature did not match the development publisher: ' + $target.Key)
            }
            $version = (Get-Item -LiteralPath $installer).VersionInfo.ProductVersion
            if ($version -ne $package.version) { throw ('Installer version mismatch: ' + $version) }
            $publicApplication = if ($target.Key -eq 'server') { 'business-server' } else { 'business-app' }
            & (Join-Path $PSScriptRoot 'publish-download-package.ps1') -Application $publicApplication -Installer $installer -Channel beta -Architecture x64
            [pscustomobject]@{ Application=$package.name;Version=$version;Channel='beta';Signature=$signature.Status.ToString();Installer=$installer;SHA256=(Get-FileHash -LiteralPath $installer -Algorithm SHA256).Hash } | ConvertTo-Json -Compress
        } finally { Pop-Location }
    }
} finally {
    foreach ($name in $names) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
    $previous.Clear()
}
