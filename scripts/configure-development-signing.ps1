# Creates a local-test identity only. Never use it as a public production publisher.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$signingDirectory = Join-Path $env:LOCALAPPDATA 'MarketLens\DevelopmentSigning'
if (Test-Path -LiteralPath $signingDirectory) {
    if ((Get-Item -LiteralPath $signingDirectory).Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'The signing directory must not be a link.'
    }
}
New-Item -ItemType Directory -Path $signingDirectory -Force | Out-Null
$acl = New-Object Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true, $false)
$currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent().User
foreach ($identity in @($currentIdentity, (New-Object Security.Principal.SecurityIdentifier('S-1-5-18')))) {
    $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $signingDirectory -AclObject $acl
$metadataPath = Join-Path $signingDirectory 'settings.json'
$pfxPath = Join-Path $signingDirectory 'marketlens-development.pfx'
$publicPath = Join-Path $signingDirectory 'marketlens-development.cer'
$passwordPath = Join-Path $signingDirectory 'password.dpapi'
if (Test-Path -LiteralPath $metadataPath) {
    $metadata = Get-Content -LiteralPath $metadataPath -Raw | ConvertFrom-Json
    if ($metadata.purpose -ne 'local-development-only') { throw 'Unexpected signing configuration.' }
    $certificate = Get-Item -LiteralPath ('Cert:\CurrentUser\My\' + $metadata.thumbprint)
    if (-not $certificate.HasPrivateKey -or $certificate.NotAfter -le (Get-Date)) { throw 'Existing development certificate is expired or missing its key.' }
    foreach ($file in @($pfxPath, $publicPath, $passwordPath)) {
        if (-not (Test-Path -LiteralPath $file)) { throw 'Signing configuration is incomplete; no key was overwritten.' }
    }
} else {
    foreach ($file in @($pfxPath, $publicPath, $passwordPath)) {
        if (Test-Path -LiteralPath $file) { throw 'An incomplete existing signing setup needs review; no key was overwritten.' }
    }
    $certificate = New-SelfSignedCertificate -Type CodeSigningCert -Subject 'CN=MarketLens Development Test Signing' -FriendlyName 'MarketLens DEVELOPMENT ONLY' -CertStoreLocation 'Cert:\CurrentUser\My' -KeyAlgorithm RSA -KeyLength 3072 -HashAlgorithm SHA256 -KeyExportPolicy Exportable -NotAfter (Get-Date).AddYears(1)
    $random = New-Object byte[] 48
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($random) } finally { $generator.Dispose() }
    $passwordText = [Convert]::ToBase64String($random)
    $password = ConvertTo-SecureString -String $passwordText -AsPlainText -Force
    try {
        Export-PfxCertificate -Cert $certificate -FilePath $pfxPath -Password $password -ChainOption EndEntityCertOnly | Out-Null
        Export-Certificate -Cert $certificate -FilePath $publicPath | Out-Null
        [IO.File]::WriteAllText($passwordPath, ($password | ConvertFrom-SecureString))
    } finally {
        $password.Dispose()
        $passwordText = $null
        [Array]::Clear($random, 0, $random.Length)
    }
    $metadata = [ordered]@{ purpose = 'local-development-only'; thumbprint = $certificate.Thumbprint; expires = $certificate.NotAfter.ToUniversalTime().ToString('o') }
    [IO.File]::WriteAllText($metadataPath, ($metadata | ConvertTo-Json))
}
# Trust is limited to this Windows account; nothing is installed machine-wide.
foreach ($store in @('Root', 'TrustedPublisher')) {
    $certificatePath = 'Cert:\CurrentUser\' + $store + '\' + $certificate.Thumbprint
    if (-not (Test-Path -LiteralPath $certificatePath)) {
        Import-Certificate -FilePath $publicPath -CertStoreLocation ('Cert:\CurrentUser\' + $store) | Out-Null
    }
}
$probe = Join-Path $signingDirectory 'signature-check.ps1'
[IO.File]::WriteAllText($probe, "# Harmless local signature verification fixture.\r\nWrite-Output 'MarketLens development signing check'\r\n")
$signature = Set-AuthenticodeSignature -FilePath $probe -Certificate $certificate -HashAlgorithm SHA256
if ($signature.Status -ne 'Valid') { throw ('Development signature verification failed: ' + $signature.Status) }
Write-Output ('Development certificate configured. Thumbprint: ' + $certificate.Thumbprint)
Write-Output ('Expires: ' + $certificate.NotAfter.ToString('yyyy-MM-dd'))
Write-Output 'Trust scope: current Windows account only. Signature verification: Valid.'
