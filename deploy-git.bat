@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: Sesuaikan sekali bila server berbeda.
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "REMOTE_DIR=C:\laragon\www\website-cdk\geomap"
set BRANCH=master
set GIT_REPO_URL=https://github.com/adhito-nugroho/geomap.git

:: Path penuh di server (SSH Windows PATH sering kosong/minimal)
set "REMOTE_GIT=C:\laragon\bin\git\cmd\git.exe"

echo ======================================================
echo GEOMAP CDK — DEPLOY GIT PULL + MIGRASI DB
echo ======================================================

:: Pengaman: config.php (kredensial) tidak boleh terlacak git
git ls-files config.php | findstr /i /c:"config.php" >nul
if not errorlevel 1 (
    echo [BAHAYA] config.php terlacak git! Batalkan dulu dengan:
    echo   git rm --cached config.php
    pause
    exit /b 1
)

:: 1. Commit (jika ada perubahan) lalu Push ke remote
echo [1/3] Mendorong perubahan lokal ke Repository...

git add -A

set "NEED_COMMIT=0"
for /f %%i in ('git diff --cached --name-only') do set "NEED_COMMIT=1"

if "!NEED_COMMIT!"=="0" (
    echo Tidak ada perubahan lokal. Skip commit, lanjut push/pull...
) else (
    set /p msg="Masukkan pesan commit (tekan Enter untuk default 'update aplikasi'): "
    if "!msg!"=="" set "msg=update aplikasi"

    git commit -m "!msg!"
    if errorlevel 1 (
        echo Gagal melakukan git commit!
        pause
        exit /b 1
    )
)

git push origin %BRANCH%
if errorlevel 1 (
    echo Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Git Fetch + Reset di Server via SSH (menimpa file lama di server)
:: reset --hard menyamakan file terlacak; clean -fd membersihkan sisa file,
:: KECUALI folder storage/geojson (data upload produksi) dan file ter-ignore
:: (config.php) yang selalu dipertahankan.
echo.
echo [2/3] Menyamakan file di server (fetch + reset)...
echo *(Jika diminta password SSH, masukkan password akun server)*
echo.

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "%REMOTE_GIT% config --global --add safe.directory C:/laragon/www/website-cdk/geomap && cd /d %REMOTE_DIR% && (%REMOTE_GIT% remote get-url origin >nul 2>&1 || %REMOTE_GIT% remote add origin %GIT_REPO_URL%) && %REMOTE_GIT% remote set-url origin %GIT_REPO_URL% && %REMOTE_GIT% fetch origin && %REMOTE_GIT% reset --hard origin/%BRANCH% && %REMOTE_GIT% clean -fd -e storage/geojson/"

if errorlevel 1 (
    echo.
    echo Git Fetch/Reset di server gagal.
    pause
    exit /b 1
)

:: 3. Jalankan migrasi database di Server via SSH (deploy-db.bat: full + seed bila awal)
echo.
echo [3/3] Menjalankan migrasi database di server...

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && deploy-db.bat"

if errorlevel 1 (
    echo.
    echo Migrasi server gagal. Cek output di atas, atau buka migrate.php
    echo di browser di server sebagai alternatif.
)

echo.
echo ======================================================
echo DEPLOYMENT DAN MIGRASI SELESAI! Cek viewer di browser.
echo ======================================================
pause
