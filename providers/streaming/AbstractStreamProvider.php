<?php
require_once __DIR__ . '/StreamProvider.php';

/**
 * Shared HTTP health-check logic — every concrete provider only needs to
 * supply its own health-check URL and episode-URL builder.
 */
abstract class AbstractStreamProvider implements StreamProvider
{
    abstract protected function getHealthCheckUrl(): string;

    public function healthCheck(): array
    {
        $start = microtime(true);
        $status = 'offline';
        $error = null;

        $ch = curl_init($this->getHealthCheckUrl());
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (AnikatsuStreamHealthCheck/1.0)',
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        $ms = (int)round((microtime(true) - $start) * 1000);

        if ($curlErr) {
            $status = 'offline';
            $error = $curlErr;
        } elseif ($httpCode >= 200 && $httpCode < 400) {
            $status = $ms > 3000 ? 'degraded' : 'healthy';
        } elseif ($httpCode >= 400) {
            $status = 'degraded';
            $error = 'HTTP ' . $httpCode;
        }

        return ['status' => $status, 'response_ms' => $ms, 'http_code' => $httpCode ?: null, 'last_error' => $error];
    }
}
