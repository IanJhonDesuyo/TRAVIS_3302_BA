# TRAFFIQ Updated PHP Files

These files replace the old hardcoded dashboard pages.

## What changed
- Removed fake dashboard numbers and fake chart data.
- Connected pages to the `travis` MySQL database using `db_connect.php`.
- Added reusable PHP helpers and layout components.
- Redesigned `monitoring.php` for one camera only.
- Added CCTV video upload support in `monitoring.php`.
- Prepared Machine Learning placeholders for `ml_predictions` and `violation_hotspots`.
- Added API examples for Python/OpenCV and ML integration.

## How to use
1. Backup your old files first.
2. Copy all files from this folder into your project root.
3. Keep your existing `css/`, `js/`, and `assets/` folders.
4. Import `traffiq_db.sql` into phpMyAdmin if you have not imported it yet.
5. Open `index.php` through XAMPP/localhost.

## Important
If your database is empty, the system will correctly show `0`, `No records found`, or `Prediction not generated yet`.

## API endpoints included
- `api/save_monitoring_log.php` — for Python/OpenCV to send vehicle count, congestion, officer presence, and collision status.
- `api/save_prediction.php` — for Python ML script to save Random Forest prediction output.
