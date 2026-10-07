<?php
declare(strict_types=1);

function enforcer_detection_is_active(array $settings, ?DateTimeImmutable $now = null): bool
{
    if ((int)($settings['enforcer_schedule_enabled'] ?? 0) !== 1) return true;
    $toMinutes = static function (string $value): ?int {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) return null;
        [$hours, $minutes] = array_map('intval', explode(':', $value));
        return $hours * 60 + $minutes;
    };
    $within = static function (int $current, int $start, int $end): bool {
        if ($start === $end) return true;
        return $start < $end ? ($current >= $start && $current < $end) : ($current >= $start || $current < $end);
    };
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $current = ((int)$now->format('H')) * 60 + (int)$now->format('i');
    $dutyStart = $toMinutes((string)($settings['enforcer_duty_start'] ?? '06:00'));
    $dutyEnd = $toMinutes((string)($settings['enforcer_duty_end'] ?? '18:00'));
    $breakStart = $toMinutes((string)($settings['enforcer_break_start'] ?? '12:00'));
    $breakEnd = $toMinutes((string)($settings['enforcer_break_end'] ?? '13:00'));
    if (in_array(null, [$dutyStart, $dutyEnd, $breakStart, $breakEnd], true)) return false;
    $onBreak = $breakStart !== $breakEnd && $within($current, $breakStart, $breakEnd);
    return $within($current, $dutyStart, $dutyEnd) && !$onBreak;
}
