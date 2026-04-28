$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$publicRoot = Join-Path $root 'public'
$viewsRoot = Join-Path $root 'resources\views'
$assetMapPath = Join-Path $root 'storage\app\static-page-assets.json'
$viewNames = @('home.blade.php', 'datenschutz.blade.php', 'impressum.blade.php')
$localHosts = @('baxi-gasthermenwartung.at')

$downloadedUrls = [System.Collections.Generic.HashSet[string]]::new()
$downloadedCssFiles = [System.Collections.Generic.HashSet[string]]::new()
$queuedUrls = [System.Collections.Generic.HashSet[string]]::new()
$queue = [System.Collections.Generic.Queue[string]]::new()
$urlTrimChars = @([char]39, [char]96, [char]34, [char]32, [char]41, [char]93, [char]44, [char]59)
$valueTrimChars = @([char]39, [char]96, [char]34, [char]32)

function Add-UrlToQueue {
    param([string] $Url)

    if ([string]::IsNullOrWhiteSpace($Url)) {
        return
    }

    $normalized = $Url.Trim($urlTrimChars).Replace('&amp;', '&')

    try {
        $uri = [Uri] $normalized
    } catch {
        return
    }

    if (-not (Should-Download -Uri $uri)) {
        return
    }

    if ($queuedUrls.Add($uri.AbsoluteUri)) {
        $queue.Enqueue($uri.AbsoluteUri)
    }
}

function Should-Download {
    param([Uri] $Uri)

    $path = $Uri.AbsolutePath.ToLowerInvariant()

    if ($path -match '\.(css|js|png|jpg|jpeg|webp|svg|gif|ico|woff|woff2|ttf|eot|otf)$') {
        return $true
    }

    return $path.Contains('/wp-content/') -or $path.Contains('/wp-includes/')
}

function Get-LocalRelativePath {
    param([Uri] $Uri)

    $path = [Uri]::UnescapeDataString($Uri.AbsolutePath.TrimStart('/'))
    if ([string]::IsNullOrWhiteSpace($path)) {
        return $null
    }

    $parts = $path -split '/'
    $safeParts = foreach ($part in $parts) {
        if ([string]::IsNullOrWhiteSpace($part)) {
            continue
        }

        $safe = $part
        foreach ($invalid in [System.IO.Path]::GetInvalidFileNameChars()) {
            $safe = $safe.Replace([string] $invalid, '_')
        }

        $safe
    }

    if ($localHosts -contains $Uri.Host.ToLowerInvariant()) {
        $relativePath = $safeParts -join '\'
    } else {
        $relativePath = (@('external', $Uri.Host) + $safeParts) -join '\'
    }

    if ($Uri.Query) {
        $ext = [System.IO.Path]::GetExtension($relativePath)
        $base = if ($ext) {
            $relativePath.Substring(0, $relativePath.Length - $ext.Length)
        } else {
            $relativePath
        }

        $sha1 = [System.Security.Cryptography.SHA1]::Create()
        try {
            $queryHash = [BitConverter]::ToString(
                $sha1.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Uri.Query))
            ).Replace('-', '').Substring(0, 8).ToLowerInvariant()
        } finally {
            $sha1.Dispose()
        }

        $relativePath = "$base.$queryHash$ext"
    }

    return $relativePath
}

function Get-LocalFilePath {
    param([Uri] $Uri)

    $relativePath = Get-LocalRelativePath -Uri $Uri
    if (-not $relativePath) {
        return $null
    }

    return Join-Path $publicRoot $relativePath
}

foreach ($viewName in $viewNames) {
    $viewPath = Join-Path $viewsRoot $viewName
    $content = Get-Content -Raw -Path $viewPath

    foreach ($match in [regex]::Matches($content, 'https?://[^\s"''<>()]+')) {
        Add-UrlToQueue -Url $match.Value
    }
}

while ($queue.Count -gt 0) {
    $url = $queue.Dequeue()
    $uri = [Uri] $url
    $localFile = Get-LocalFilePath -Uri $uri

    if (-not $localFile) {
        continue
    }

    $directory = Split-Path -Parent $localFile
    if (-not (Test-Path $directory)) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    if (-not (Test-Path $localFile)) {
        try {
            Invoke-WebRequest -Uri $uri.AbsoluteUri -OutFile $localFile
        } catch {
            Write-Warning "Skipped asset: $($uri.AbsoluteUri)"
            continue
        }
    }

    $null = $downloadedUrls.Add($uri.AbsoluteUri)

    if ($localFile.ToLowerInvariant().EndsWith('.css')) {
        $null = $downloadedCssFiles.Add($localFile)
        $css = Get-Content -Raw -Path $localFile

        foreach ($cssMatch in [regex]::Matches($css, 'url\(([^)]+)\)')) {
            $rawValue = $cssMatch.Groups[1].Value.Trim($valueTrimChars)

            if (
                [string]::IsNullOrWhiteSpace($rawValue) -or
                $rawValue.StartsWith('data:') -or
                $rawValue.StartsWith('#')
            ) {
                continue
            }

            try {
                $resolved = [Uri]::new($uri, $rawValue)
            } catch {
                continue
            }

            Add-UrlToQueue -Url $resolved.AbsoluteUri
        }
    }
}

$assetMap = @{}
foreach ($url in $downloadedUrls) {
    $uri = [Uri] $url
    $relativePath = (Get-LocalRelativePath -Uri $uri) -replace '\\', '/'
    $assetMap[$url] = '/' + $relativePath
}

foreach ($cssFile in $downloadedCssFiles) {
    $css = Get-Content -Raw -Path $cssFile

    foreach ($entry in $assetMap.GetEnumerator()) {
        $css = $css.Replace($entry.Key, $entry.Value)
    }

    Set-Content -Path $cssFile -Value $css -Encoding UTF8
}

$assetMapDir = Split-Path -Parent $assetMapPath
if (-not (Test-Path $assetMapDir)) {
    New-Item -ItemType Directory -Force -Path $assetMapDir | Out-Null
}

$assetMap | ConvertTo-Json -Depth 5 | Set-Content -Path $assetMapPath -Encoding UTF8

Write-Output "Downloaded assets: $($downloadedUrls.Count)"
Write-Output "Asset map saved to: $assetMapPath"
