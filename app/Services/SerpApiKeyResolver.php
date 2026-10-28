<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Throwable;

class SerpApiKeyResolver
{
    private ?string $resolvedKey = null;

    public function resolve(): ?string
    {
        $configuredKey = config('services.serpapi.key');
        if (is_string($configuredKey) && trim($configuredKey) !== '') {
            return trim($configuredKey);
        }

        if ($this->resolvedKey !== null) {
            return $this->resolvedKey;
        }

        $localAppData = getenv('LOCALAPPDATA');
        $encryptedKeyPath = is_string($localAppData) ? $localAppData.DIRECTORY_SEPARATOR.'SerpApi'.DIRECTORY_SEPARATOR.'api-key.dpapi' : '';
        $helperPath = base_path('scripts/read-serpapi-key.ps1');
        if (PHP_OS_FAMILY !== 'Windows' || ! is_file($encryptedKeyPath) || ! is_file($helperPath)) {
            return null;
        }

        try {
            $process = new Process([
                'powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-File', $helperPath,
            ], base_path(), ['LOCALAPPDATA' => $localAppData]);
            $process->setTimeout(5);
            $process->run();
            $key = trim($process->getOutput());

            if (! $process->isSuccessful() || $key === '' || preg_match('/[\r\n]/', $key)) {
                return null;
            }

            $this->resolvedKey = $key;

            return $this->resolvedKey;
        } catch (Throwable) {
            return null;
        }
    }
}
