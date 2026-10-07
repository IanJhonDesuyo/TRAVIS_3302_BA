<?php
declare(strict_types=1);

/**
 * Shared application configuration.
 *
 * Local development keeps the current XAMPP defaults. Production secrets may
 * be supplied either as environment variables or in config/local.php. The
 * latter is intentionally ignored by Git and blocked from web access.
 */
$travisLocalConfig = __DIR__ . '/local.php';
$travisFileConfig = is_file($travisLocalConfig) ? require $travisLocalConfig : [];
if (!is_array($travisFileConfig)) {
    throw new RuntimeException('config/local.php must return a configuration array.');
}

function travis_config_value(string $environmentKey, string $fileKey, string $default = ''): string
{
    global $travisFileConfig;

    $environmentValue = getenv($environmentKey);
    if ($environmentValue !== false && $environmentValue !== '') {
        return (string)$environmentValue;
    }

    $fileValue = $travisFileConfig[$fileKey] ?? null;
    return is_scalar($fileValue) && (string)$fileValue !== '' ? (string)$fileValue : $default;
}

function travis_db_config(): array
{
    return [
        'host' => travis_config_value('TRAVIS_DB_HOST', 'db_host', 'localhost'),
        'port' => (int)travis_config_value('TRAVIS_DB_PORT', 'db_port', '3306'),
        'name' => travis_config_value('TRAVIS_DB_NAME', 'db_name', 'travis'),
        'user' => travis_config_value('TRAVIS_DB_USER', 'db_user', 'root'),
        'pass' => travis_config_value('TRAVIS_DB_PASS', 'db_pass', ''),
    ];
}

