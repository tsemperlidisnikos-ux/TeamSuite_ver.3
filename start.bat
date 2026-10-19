@echo off
chcp 65001 >nul
cd /d "%~dp0"
title TeamSuite
set "LOCK=%TEMP%\teamsuite-dev.lock"
set "URL=http://127.0.0.1:8000"
set "VITE=http://127.0.0.1:5173"

if not exist "artisan" (
    echo Δεν βρέθηκε το artisan σε αυτόν τον φάκελο.
    pause
    exit /b 1
)

where php >nul 2>&1
if errorlevel 1 (
    echo Δεν βρέθηκε η PHP στο PATH.
    pause
    exit /b 1
)

where composer >nul 2>&1
if errorlevel 1 (
    echo Δεν βρέθηκε ο Composer στο PATH.
    pause
    exit /b 1
)

where node >nul 2>&1
if errorlevel 1 (
    echo Δεν βρέθηκε το Node.js στο PATH.
    pause
    exit /b 1
)

REM Αν υπάρχει stale public\hot από παλιό Vite (::1), καθάρισέ το — αλλιώς η σελίδα μένει κενή.
if exist "public\hot" (
    findstr /C:"127.0.0.1" "public\hot" >nul 2>&1
    if errorlevel 1 (
        echo Καθαρισμός παλιού Vite hot file...
        del "public\hot" >nul 2>&1
    )
)

curl.exe -s -o NUL -f --max-time 2 "%URL%" >nul 2>&1
if errorlevel 1 goto :NeedStart

REM PHP απαντά — έλεγξε και Vite. Χωρίς Vite η σελίδα φαίνεται «σπασμένη».
curl.exe -s -o NUL -f --max-time 2 "%VITE%" >nul 2>&1
if not errorlevel 1 goto :OpenNow

echo Ο server στο :8000 τρέχει, αλλά το Vite στο :5173 όχι.
echo Επανεκκίνηση dev stack...
call :StopDev
goto :StartFresh

:NeedStart
powershell.exe -NoProfile -Command "if (Test-Path $env:TEMP\teamsuite-dev.lock) { $age = (Get-Date) - (Get-Item $env:TEMP\teamsuite-dev.lock).LastWriteTime; if ($age.TotalSeconds -lt 180) { exit 0 } }; exit 1"
if not errorlevel 1 goto :WaitAndOpen

:StartFresh
echo 1>"%LOCK%"

echo.
echo TeamSuite
echo Ξεκινά ο server και το Vite.
echo Ο browser θα ανοίξει στο %URL%
echo Κλείσε αυτό το παράθυρο για να σταματήσει η εφαρμογή.
echo.

start "TeamSuite browser" /min powershell.exe -NoProfile -WindowStyle Hidden -Command "for ($i=0; $i -lt 90; $i++) { try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000' -TimeoutSec 2 | Out-Null; try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:5173' -TimeoutSec 2 | Out-Null } catch {} ; Start-Process 'http://127.0.0.1:8000'; exit 0 } catch { Start-Sleep -Seconds 1 } }"

composer run dev
set "EXITCODE=%ERRORLEVEL%"
del "%LOCK%" >nul 2>&1
if exist "public\hot" del "public\hot" >nul 2>&1
if not "%EXITCODE%"=="0" (
    echo.
    echo Η εφαρμογή δεν ξεκίνησε. Δες το μήνυμα από πάνω.
    pause
)
exit /b %EXITCODE%

:OpenNow
start "" "%URL%"
echo Η εφαρμογή τρέχει ήδη: %URL%
timeout /t 3 /nobreak >nul
exit /b 0

:WaitAndOpen
echo Η εφαρμογή ξεκινά ήδη. Ο browser θα ανοίξει μόλις είναι έτοιμη.
start "TeamSuite browser" /min powershell.exe -NoProfile -WindowStyle Hidden -Command "for ($i=0; $i -lt 90; $i++) { try { Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000' -TimeoutSec 2 | Out-Null; Start-Process 'http://127.0.0.1:8000'; exit 0 } catch { Start-Sleep -Seconds 1 } }"
exit /b 0

:StopDev
REM Σταμάτα μόνο processes αυτού του φακέλου (serve / queue / vite / concurrently).
powershell.exe -NoProfile -Command ^
  "$root = (Resolve-Path '.').Path; " ^
  "Get-CimInstance Win32_Process | Where-Object { " ^
  "  $_.CommandLine -and $_.CommandLine.Contains($root) -and ($_.Name -match '^(php|node)\\.exe$') " ^
  "} | ForEach-Object { try { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } catch {} }; " ^
  "Start-Sleep -Seconds 1; " ^
  "if (Test-Path 'public\\hot') { Remove-Item 'public\\hot' -Force -ErrorAction SilentlyContinue }; " ^
  "if (Test-Path $env:TEMP\\teamsuite-dev.lock) { Remove-Item $env:TEMP\\teamsuite-dev.lock -Force -ErrorAction SilentlyContinue }"
exit /b 0
