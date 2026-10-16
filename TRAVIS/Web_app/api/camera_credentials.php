<?php
declare(strict_types=1);

function camera_credential_key(): string
{
    $secret = (string)(getenv('TRAVIS_SERVICE_API_KEY') ?: '');
    if (strlen($secret) < 32) {
        throw new RuntimeException('Camera credential encryption is not configured.');
    }
    return hash('sha256', 'TRAVIS-CAMERA-CREDENTIAL|' . $secret, true);
}

function camera_encrypt_password(string $password): string
{
    if ($password === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $password,
        'aes-256-gcm',
        camera_credential_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'TRAVIS-CAMERA-V1'
    );
    if ($ciphertext === false) throw new RuntimeException('Unable to protect the camera password.');
    return base64_encode($iv . $tag . $ciphertext);
}

function camera_decrypt_password(?string $encrypted): string
{
    if (!$encrypted) return '';
    $payload = base64_decode($encrypted, true);
    if ($payload === false || strlen($payload) < 29) return '';
    $password = openssl_decrypt(
        substr($payload, 28),
        'aes-256-gcm',
        camera_credential_key(),
        OPENSSL_RAW_DATA,
        substr($payload, 0, 12),
        substr($payload, 12, 16),
        'TRAVIS-CAMERA-V1'
    );
    return is_string($password) ? $password : '';
}

function camera_ensure_password_column(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM cameras LIKE 'camera_password_encrypted'");
    if ($result && $result->num_rows === 0) {
        if (!$conn->query('ALTER TABLE cameras ADD COLUMN camera_password_encrypted TEXT NULL AFTER camera_username')) {
            throw new RuntimeException('Unable to prepare encrypted camera credentials.');
        }
    }
}

