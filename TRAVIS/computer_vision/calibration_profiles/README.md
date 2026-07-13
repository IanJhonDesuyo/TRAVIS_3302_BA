# Calibration profiles

Each camera or uploaded-video viewpoint should have its own JSON profile. All
coordinates are normalized from `0.0` to `1.0`, so the same profile works at
different resolutions from the same camera angle.

- `road_roi`: polygon limiting collision analysis to the roadway.
- `inbound_line` / `outbound_line`: direction-counting lines.
- `officer_zone`: polygon for the designated traffic-enforcer post.
- `officer.enabled`: enables Phase 1 person-presence detection in that zone.
- `collision.enabled`: explicitly opts that calibrated source into collision analysis.
- Collision thresholds are conservative starting values and must be validated
  against representative footage from that viewpoint.

Set `CALIBRATION_PROFILE` in `computer_vision/config.py` to a profile path, or
pass `--calibration-profile <path>` to `detect_video.py`. Collision detection
also requires either `collision.enabled: true`, `ENABLE_COLLISION_DETECTION =
True`, or the `--enable-collision` command-line flag.

When no profile is selected, TRAVIS retains the original lines from
`config.py`, uses the full frame as the road ROI, and keeps collision detection
disabled. This is the compatibility-safe fallback.
