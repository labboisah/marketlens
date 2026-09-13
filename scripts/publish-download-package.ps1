# Verify a Windows installer and publish its complete setup ZIP into the Platform.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][ValidateSet('business-server','business-app')][string]$Application,
    [Parameter(Mandatory=$true)][string]$Installer,
    [ValidateSet('stable','beta','internal')][string]$Channel = 'beta',
    [ValidateSet('x64','arm64','ia32')][string]$Architecture = 'x64'
)
$ErrorActionPreference = 'Stop'
$installerPath = (Resolve-Path -LiteralPath $Installer).Path
$signature = Get-AuthenticodeSignature -LiteralPath $installerPath
if ($signature.Status -ne 'Valid') { throw 'The installer must have a valid, trusted signature on this build computer.' }
$releaseVersion = (Get-Item -LiteralPath $installerPath).VersionInfo.ProductVersion
if ($releaseVersion -notmatch '^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$') { throw 'The installer product version is invalid.' }
$platformRoot = Split-Path -Parent $PSScriptRoot
Push-Location $platformRoot
try {
    & php artisan releases:pack $Application $installerPath "--release-version=$releaseVersion" "--channel=$Channel" "--architecture=$Architecture"
    if ($LASTEXITCODE -ne 0) { throw 'Download package generation failed.' }
} finally { Pop-Location }
