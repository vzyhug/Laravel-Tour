$ErrorActionPreference = 'Stop'
$keyPath = Join-Path $env:LOCALAPPDATA 'SerpApi/api-key.dpapi'
$secureKey = $null
try {
    $encryptedKey = (Get-Content -LiteralPath $keyPath -Raw).Trim()
    $secureKey = $encryptedKey | ConvertTo-SecureString
    $plainKey = [System.Net.NetworkCredential]::new('', $secureKey).Password
    if ([string]::IsNullOrWhiteSpace($plainKey) -or $plainKey -match '[\r\n]') {
        throw 'Stored SerpApi key is empty or invalid.'
    }
    [Console]::Out.Write($plainKey)
}
finally {
    $plainKey = $null
    if ($null -ne $secureKey) { $secureKey.Dispose() }
}
