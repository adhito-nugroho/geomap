@echo off
setlocal EnableExtensions
rem ============================================================
rem  deploy-db.bat : jalankan migrasi database project
rem  Cara pakai:
rem    deploy-db.bat                         ^(pakai folder file ini^)
rem    deploy-db.bat "C:\laragon\www\website-cdk\geomap"   ^(folder di server^)
rem  Menjalankan berurutan: migrate.php lalu database\migrate_002.php
rem  (keduanya idempotent, aman diulang).
rem  Syarat di folder target: config.php sudah diisi (salin dari
rem  config.example.php), PHP + MySQL Laragon jalan.
rem ============================================================

if "%~1"=="" (
  set "ROOT=%~dp0"
) else (
  set "ROOT=%~1"
)
cd /d "%ROOT%" || (echo [GAGAL] Tidak bisa masuk folder %ROOT% & exit /b 1)
echo [INFO] Folder project: %CD%

where php >nul 2>nul
if errorlevel 1 (echo [GAGAL] php tidak ditemukan di PATH. & exit /b 1)

if not exist "migrate.php" (
  echo [GAGAL] migrate.php tidak ditemukan. Pastikan argumen menunjuk folder project.
  exit /b 1
)
if not exist "config.php" (
  echo [GAGAL] config.php tidak ada. Salin config.example.php menjadi config.php
  echo        lalu isi kredensial database sebelum migrasi.
  exit /b 1
)

echo [INFO] 1/2 migrate.php ...
php migrate.php
if errorlevel 1 (echo [GAGAL] migrate.php gagal. & exit /b 1)

if exist "database\migrate_002.php" (
  echo [INFO] 2/2 database\migrate_002.php ...
  php database\migrate_002.php
  if errorlevel 1 (echo [GAGAL] migrate_002.php gagal. & exit /b 1)
) else (
  echo [INFO] 2/2 migrate_002.php tidak ada, lewati.
)

echo [OK] Migrasi database selesai.
echo Verifikasi: buka admin/index.php dan pastikan tree group/layer tampil.
endlocal
