@echo off
setlocal EnableExtensions
rem ============================================================
rem  deploy-git.bat : commit + push project ke GitHub
rem  Repo   : https://github.com/adhito-nugroho/geomap.git
rem  Cara pakai (dari folder project):
rem    deploy-git.bat                 (pesan commit otomatis: Deploy tgl_jam)
rem    deploy-git.bat "Pesan custom"
rem  Catatan: config.php TIDAK ikut ter-push (ada di .gitignore).
rem ============================================================

set "REPO_URL=https://github.com/adhito-nugroho/geomap.git"
set "ROOT=%~dp0"
cd /d "%ROOT%" || (echo [GAGAL] Tidak bisa masuk folder %ROOT% & exit /b 1)

where git >nul 2>nul
if errorlevel 1 (echo [GAGAL] git tidak ditemukan di PATH. & exit /b 1)

if not exist ".git" (
  echo [INFO] Repo git belum ada, inisialisasi baru...
  git init || exit /b 1
  git branch -M main
)

for /f %%b in ('git branch --show-current 2^>nul') do set "BRANCH=%%b"
if not defined BRANCH set "BRANCH=main"
echo [INFO] Branch lokal: %BRANCH%

git remote get-url origin >nul 2>nul
if errorlevel 1 (
  echo [INFO] Menambahkan remote origin...
  git remote add origin "%REPO_URL%" || exit /b 1
)

rem --- Pengaman: config.php (kredensial) tidak boleh terlacak git ---
git ls-files config.php | findstr /i /c:"config.php" >nul
if not errorlevel 1 (
  echo [BAHAYA] config.php terlacak git! Batalkan dulu dengan:
  echo   git rm --cached config.php
  exit /b 1
)

git add -A
echo ---------- status ----------
git status --short
echo ----------------------------

git diff --cached --quiet
if errorlevel 1 goto :DOCOMMIT
echo [INFO] Tidak ada perubahan untuk di-commit.
goto :PULLPUSH

:DOCOMMIT
if "%~1"=="" goto :AUTOMSG
set "MSG=%~1"
goto :DOMMIT
:AUTOMSG
for /f %%t in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HH-mm"') do set "MSG=Deploy %%t"
:DOMMIT
git commit -m "%MSG%" || exit /b 1

:PULLPUSH
rem Tarik dulu HANYA bila branch sudah ada di remote (push pertama tidak perlu pull)
git ls-remote --heads origin "%BRANCH%" | findstr /c:"refs/heads/" >nul
if errorlevel 1 goto :FIRSTPUSH
echo [INFO] Pull --rebase dari origin/%BRANCH%...
git pull --rebase origin "%BRANCH%" || exit /b 1
goto :DOPUSH

:FIRSTPUSH
echo [INFO] Branch remote belum ada, lewati pull (push awal).

:DOPUSH
echo [INFO] Push ke origin/%BRANCH%...
git push -u origin "%BRANCH%" || exit /b 1

rem --- Update server lokal bila ada: pull + migrasi skema SAJA tanpa seed ---
rem Atur folder server di bawah, atau via env GEOMAP_SERVER_DIR sebelum menjalankan.
if not defined GEOMAP_SERVER_DIR set "GEOMAP_SERVER_DIR=C:\laragon\www\website-cdk\geomap"
if not exist "%GEOMAP_SERVER_DIR%\.git" goto :NOSERVER
echo [INFO] Update server: %GEOMAP_SERVER_DIR%
git -C "%GEOMAP_SERVER_DIR%" pull --rebase origin "%BRANCH%" || exit /b 1
call "%ROOT%deploy-db.bat" "%GEOMAP_SERVER_DIR%" --migrations-only || exit /b 1
echo [OK] Deploy selesai: kode ter-push, server ter-pull + termigrasi.
goto :ENDOK

:NOSERVER
echo [INFO] Folder server tidak ditemukan di mesin ini, lewati update server.
echo        Petunjuk: jalankan deploy-git.bat ini JUGA di server - di sana ia
echo        akan pull + migrasi otomatis (push tidak ada yang baru).
echo [OK] Deploy git selesai (push saja).

:ENDOK
endlocal
