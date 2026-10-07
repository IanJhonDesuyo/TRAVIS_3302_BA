"""Time-window gating for traffic-enforcer presence detection."""

from datetime import datetime


def _minutes(value: str) -> int:
    hours, minutes = str(value).split(":", 1)
    return int(hours) * 60 + int(minutes)


def _within(now: int, start: int, end: int) -> bool:
    if start == end:
        return True
    return start <= now < end if start < end else now >= start or now < end


def is_enforcer_duty_active(settings: dict, now: datetime | None = None) -> bool:
    """Return true during duty hours, excluding the configured break window."""
    if not bool(int(settings.get("enforcer_schedule_enabled", 0))):
        return True
    try:
        current = now or datetime.now()
        minute = current.hour * 60 + current.minute
        duty = _within(minute, _minutes(settings.get("enforcer_duty_start", "06:00")), _minutes(settings.get("enforcer_duty_end", "18:00")))
        break_start = _minutes(settings.get("enforcer_break_start", "12:00"))
        break_end = _minutes(settings.get("enforcer_break_end", "13:00"))
        on_break = break_start != break_end and _within(minute, break_start, break_end)
        return duty and not on_break
    except (TypeError, ValueError):
        return False
