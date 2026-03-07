@echo off
setlocal

set "MNTPACK_ROOT=%MNTPACK_HOME%"
if "%MNTPACK_ROOT%"=="" set "MNTPACK_ROOT=%USERPROFILE%\.mntpack"

set "REPO_DIR="
for /D %%D in ("%MNTPACK_ROOT%\repos\*__php-asm") do (
    if exist "%%~fD\src\phc.php" (
        set "REPO_DIR=%%~fD"
        goto :run
    )
)

echo php-asm checkout not found in "%MNTPACK_ROOT%\repos". 1>&2
echo Re-run: mntpack sync owner/php-asm 1>&2
exit /b 1

:run
php "%REPO_DIR%\src\phc.php" %*
exit /b %ERRORLEVEL%
