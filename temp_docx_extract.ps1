$path = 'C:\Users\HOMEPC\Documents\Marketlens\Extend the existing MarketLens Cloud Platform with.docx'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($path)
$zip.Entries | ForEach-Object { $_.FullName }
$zip.Dispose()
