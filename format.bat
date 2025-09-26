@echo off
echo === PHP Code Formatting ===
echo.

echo Installing dependencies if needed...
call composer install --no-interaction

echo.
echo Running PHP Code Beautifier and Fixer...
call vendor\bin\phpcbf --standard=WordPress *.php

echo.
echo Code formatting complete! ✓
pause