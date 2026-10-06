@echo off
setlocal EnableExtensions
rem ============================================================
rem  deploy-db.bat : migrasi database project (idempotent, aman diulang)
rem  Cara pakai:
rem    deploy-db.bat                         (folder file ini, full: awal)
rem    deploy-db.bat "C:\laragon\www\website-cdk\geomap"
rem    deploy-db.bat --migrations-only       (hanya migrate_002 dst, tanpa seed)
rem    deploy-db.bat "C:\...\geomap" --migrations-only
rem  Full = migrate.php (skema + seed HANYA bila DB kosong) lalu database\migrate_*.php.
rem  Syarat: config.php sudah diisi, PHP + MySQL jalan.
rem ============================================================

set "ROOT=%~dp0"
set "MODE=full"
:ARGS
if "%~1"=="" goto :ARGS_DONE
if /i "%~1"=="--migrations-only" set "MODE=migonly"
if /i not "%~1"=="--migrations-only" set "ROOT=%~1"
shift
goto :ARGS
:ARGS_DONE

cd /d "%ROOT%" || (echo [GAGAL] Tidak bisa masuk folder %ROOT% & exit /b 1)
echo [INFO] Folder project: %CD%
echo [INFO] Mode: %MODE%

rem --- Cari php.exe: PATH dulu, lalu folder PHP bawaan Laragon ---
set "PHPBIN=php"
where php >nul 2>nul
if not errorlevel 1 goto :PHPOK
for /d %%d in ("C:\laragon\bin\php\php-*" "D:\laragon\bin\php\php-*") do (
  if exist "%%~d\php.exe" set "PHPBIN=%%~d\php.exe"
)
if "%PHPBIN%"=="php" (
  echo [GAGAL] php tidak ditemukan. Tambahkan PHP ke PATH atau edit
  echo        variabel PHPBIN di deploy-db.bat agar menunjuk php.exe Laragon.
  exit /b 1
)
:PHPOK
echo [INFO] PHP: %PHPBIN%

if not exist "migrate.php" (
  echo [GAGAL] migrate.php tidak ditemukan. Pastikan argumen menunjuk folder project.
  exit /b 1
)
if not exist "config.php" (
  echo [GAGAL] config.php tidak ada. Salin config.example.php menjadi config.php
  echo        lalu isi kredensial database sebelum migrasi.
  exit /b 1
)

if "%MODE%"=="migonly" goto :MIGONLY
echo [INFO] migrate.php (full: skema + seed bila DB kosong) ...
"%PHPBIN%" migrate.php
if errorlevel 1 (echo [GAGAL] migrate.php gagal. & exit /b 1)

:MIGONLY
echo [INFO] Migrasi skema database\migrate_*.php berurutan ...
for %%f in (database\migrate_*.php) do (
  echo [INFO] - %%~nxf ...
  "%PHPBIN%" "%%~f"
  if errorlevel 1 (echo [GAGAL] %%~nxf gagal. & exit /b 1)
)

echo [OK] Migrasi database selesai.
echo Verifikasi: buka admin/index.php dan pastikan tree group/layer tampil.
endlocal
