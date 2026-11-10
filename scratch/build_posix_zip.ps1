# Script to build production zip package with forward slashes (POSIX compatible) for Hostinger cPanel / File Manager
$rootDir = "c:\laragon\www\tetherbrew"
$zipPath = "c:\laragon\www\tetherbrew\tetherbrew_production.zip"

if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipFile = [System.IO.File]::Create($zipPath)
$archive = New-Object System.IO.Compression.ZipArchive($zipFile, [System.IO.Compression.ZipArchiveMode]::Create)

$excludeFolders = @(
    '\.git\',
    '\.gemini\',
    '\scratch\',
    '\node_modules\',
    '\storage\logs\',
    '\storage\cache\'
)

$excludeFiles = @(
    'tetherbrew_production.zip',
    '.DS_Store',
    'Thumbs.db'
)

$allFiles = Get-ChildItem -Path $rootDir -Recurse -File

$count = 0
foreach ($file in $allFiles) {
    $fullPath = $file.FullName
    $relPath = $fullPath.Substring($rootDir.Length).TrimStart('\')
    
    $skip = $false
    foreach ($ef in $excludeFolders) {
        if ($fullPath -match [regex]::Escape($ef)) {
            $skip = $true
            break
        }
    }
    if ($skip) { continue }
    
    if ($excludeFiles -contains $file.Name) {
        continue
    }

    # Format entry name with standard POSIX forward slashes
    $entryName = $relPath.Replace('\', '/')
    
    $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
    $entryStream = $entry.Open()
    $fileStream = [System.IO.File]::OpenRead($fullPath)
    $fileStream.CopyTo($entryStream)
    $fileStream.Close()
    $entryStream.Close()
    $count++
}

$archive.Dispose()
$zipFile.Close()

$zipSize = (Get-Item $zipPath).Length / 1MB
Write-Host "Successfully created $zipPath"
Write-Host "Total files packaged: $count"
Write-Host "Zip size: $([math]::Round($zipSize, 2)) MB"

