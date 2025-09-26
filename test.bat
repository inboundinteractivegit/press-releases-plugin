@echo off
echo === PHP WordPress Plugin Testing ===
echo.

echo [1/4] Installing dependencies...
call composer install --no-interaction

echo.
echo [2/4] Running PHP CodeSniffer (Linting)...
call vendor\bin\phpcs --standard=WordPress *.php
if %ERRORLEVEL% NEQ 0 (
    echo LINT FAILED! Fix the issues above.
    pause
    exit /b 1
)

echo.
echo [3/4] Running PHPUnit Tests...
call vendor\bin\phpunit
if %ERRORLEVEL% NEQ 0 (
    echo TESTS FAILED! Check the test results above.
    pause
    exit /b 1
)

echo.
echo [4/4] All tests passed! ✓
pause