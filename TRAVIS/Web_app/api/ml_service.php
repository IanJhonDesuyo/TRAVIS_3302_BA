<?php
declare(strict_types=1);

function ensure_ml_service_running(): bool {
    $connection = @fsockopen('127.0.0.1', 5001, $errorCode, $errorMessage, 0.15);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }

    if (PHP_OS_FAMILY !== 'Windows') return false;

    $projectRoot = dirname(__DIR__, 2);
    $python = $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'pythonw.exe';
    $apiScript = $projectRoot . DIRECTORY_SEPARATOR . 'Machine_Learning' . DIRECTORY_SEPARATOR . 'api.py';
    if (!is_file($python) || !is_file($apiScript)) return false;

    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'travis_ml_api_start.lock';
    $lock = @fopen($lockPath, 'c+');
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        return false;
    }

    rewind($lock);
    $lastAttempt = (int) trim((string) stream_get_contents($lock));
    if ($lastAttempt > 0 && time() - $lastAttempt < 15) {
        flock($lock, LOCK_UN);
        fclose($lock);
        return false;
    }

    rewind($lock);
    ftruncate($lock, 0);
    fwrite($lock, (string) time());
    fflush($lock);

    $escapedPython = str_replace("'", "''", $python);
    $escapedScript = str_replace("'", "''", $apiScript);
    $command = "powershell -NoProfile -WindowStyle Hidden -Command \"Start-Process -FilePath '$escapedPython' -ArgumentList '$escapedScript' -WindowStyle Hidden\"";
    $process = @popen($command, 'r');
    $started = is_resource($process);
    if ($started) pclose($process);

    flock($lock, LOCK_UN);
    fclose($lock);
    return $started;
}
