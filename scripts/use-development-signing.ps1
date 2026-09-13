# Dot-source this file to configure the current PowerShell session for test builds.
[CmdletBinding()]
param([switch]$VerifyOnly)
$ErrorActionPreference = 'Stop'
$signingDirectory = Join-Path $env:LOCALAPPDATA 'MarketLens\DevelopmentSigning'
$metadata = Get-Content -LiteralPath (Join-Path $signingDirectory 'settings.json') -Raw | ConvertFrom-Json
if ($metadata.purpose -ne 'local-development-only') { throw 'Unexpected certificate purpose.' }
$certificate = Get-Item -LiteralPath ('Cert:\CurrentUser\My\' + $metadata.thumbprint)
if (-not $certificate.HasPrivateKey -or $certificate.NotAfter -le (Get-Date)) { throw 'Development certificate is unavailable or expired.' }
$pfxPath = Join-Path $signingDirectory 'marketlens-development.pfx'
$protectedPassword = Get-Content -LiteralPath (Join-Path $signingDirectory 'password.dpapi') -Raw
$password = ConvertTo-SecureString -String $protectedPassword
try {
    $pfx = Get-PfxData -FilePath $pfxPath -Password $password
    if ($pfx.EndEntityCertificates.Thumbprint -notcontains $metadata.thumbprint) { throw 'Certificate file does not match the configured publisher.' }
    if (-not $VerifyOnly) {
        $env:WINDOWS_CERTIFICATE_FILE = $pfxPath
        $env:WINDOWS_CERTIFICATE_PASSWORD = [Net.NetworkCredential]::new('', $password).Password
        $env:WINDOWS_PUBLISHER_THUMBPRINTS = $metadata.thumbprint
        $env:BUSINESS_UPDATE_CHANNEL = 'beta'
        $env:MARKETLENS_UPDATE_CHANNEL = 'beta'
        $env:MARKETLENS_ALLOW_UNSIGNED_DEVELOPMENT = '0'
        Write-Output 'Local test signing loaded for this shell; both applications use the beta channel.'
    } else {
        Write-Output 'Certificate and encrypted password verified; no environment settings changed.'
    }
} finally {
    $password.Dispose()
    $protectedPassword = $null
}
