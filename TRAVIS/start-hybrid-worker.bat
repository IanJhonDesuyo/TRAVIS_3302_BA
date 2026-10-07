@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
  echo TRAVIS virtual environment was not found.
  echo Expected: %CD%\.venv\Scripts\python.exe
  pause
  exit /b 1
)

if not exist "storage\service_api.key" (
  echo TRAVIS service API key was not found.
  echo Expected: %CD%\storage\service_api.key
  pause
  exit /b 1
)

echo Starting the TRAVIS hosted ML/CV worker...
echo Keep this window open while hosted AI features are in use.
echo.
".venv\Scripts\python.exe" "tools\hybrid_worker.py"

echo.
echo The TRAVIS worker has stopped.
pause
