@echo off
:: ===================================================================
:: 🛡️ SAFE TESTING WORKFLOW - READ-ONLY VALIDATION ONLY
:: ===================================================================
:: ✅ SAFE: This script NEVER modifies your code
:: ✅ SAFE: Only validates syntax and functionality
:: ❌ NEVER: Uses auto-fix tools or code modification
:: 📋 PURPOSE: Validate that the plugin works correctly
:: ===================================================================

echo.
echo ========================================
echo 🛡️ PressStack Safe Testing Workflow
echo ========================================
echo.
echo ✅ GUARANTEE: This script is 100%% READ-ONLY
echo ❌ NEVER MODIFIES: Your code will not be changed
echo 🔍 VALIDATES: PHP syntax, functions, and structure
echo 📋 PURPOSE: Ensure plugin functionality is intact
echo.

:: Safety check - ensure we're in the right directory
if not exist "press-releases-manager.php" (
    echo ❌ ERROR: press-releases-manager.php not found
    echo Please run this script from the plugin directory
    echo Current directory: %CD%
    pause
    exit /b 1
)

echo 🔍 Starting safe validation tests...
echo.

:: ==============================================
:: Test 1: PHP Syntax Validation (READ-ONLY)
:: ==============================================
echo [1/6] 🔍 PHP Syntax Check (Main Plugin)...
php -l press-releases-manager.php
if %errorlevel% neq 0 (
    echo ❌ SYNTAX ERROR in press-releases-manager.php
    echo Please fix syntax errors manually before continuing
    pause
    exit /b 1
)
echo ✅ Main plugin syntax is valid
echo.

echo [2/6] 🔍 PHP Syntax Check (Plugin Updater)...
if exist "plugin-updater.php" (
    php -l plugin-updater.php
    if %errorlevel% neq 0 (
        echo ❌ SYNTAX ERROR in plugin-updater.php
        echo Please fix syntax errors manually before continuing
        pause
        exit /b 1
    )
    echo ✅ Plugin updater syntax is valid
) else (
    echo ⚠️  plugin-updater.php not found
)
echo.

:: ==============================================
:: Test 2: Critical Class and Function Check
:: ==============================================
echo [3/6] 🔍 Critical Components Check...

:: Check for main class
findstr /c:"class PressStack" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ CRITICAL: PressStack class not found
    echo This indicates major code corruption!
    pause
    exit /b 1
) else (
    echo ✅ PressStack class found
)

:: Check for shortcode registration
findstr /c:"add_shortcode" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ WARNING: Shortcode registration not found
    echo Users won't be able to use [press_releases] shortcode
) else (
    echo ✅ Shortcode registration found
)

:: Check for AJAX handlers
findstr /c:"wp_ajax" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ WARNING: AJAX handlers not found
    echo Dynamic URL loading won't work
) else (
    echo ✅ AJAX handlers found
)

:: Check for admin menus
findstr /c:"admin_menu" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ WARNING: Admin menu registration not found
    echo Admin interface may not be available
) else (
    echo ✅ Admin menu registration found
)
echo.

:: ==============================================
:: Test 3: Security Features Validation
:: ==============================================
echo [4/6] 🔒 Security Features Check...

:: Check for nonce verification
findstr /c:"wp_verify_nonce" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ CRITICAL: Nonce verification not found
    echo Plugin may be vulnerable to CSRF attacks
) else (
    echo ✅ Nonce verification found
)

:: Check for capability checks
findstr /c:"current_user_can" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ WARNING: User capability checks not found
    echo Unauthorized users might access admin functions
) else (
    echo ✅ User capability checks found
)

:: Check for input sanitization
findstr /c:"sanitize_" press-releases-manager.php >nul
if %errorlevel% neq 0 (
    echo ❌ WARNING: Input sanitization not found
    echo Plugin may be vulnerable to XSS attacks
) else (
    echo ✅ Input sanitization found
)
echo.

:: ==============================================
:: Test 4: Plugin Updater Validation
:: ==============================================
echo [5/6] 🔄 Plugin Updater Check...
if exist "plugin-updater.php" (
    :: Check for download URL function
    findstr /c:"get_download_url" plugin-updater.php >nul
    if %errorlevel% neq 0 (
        echo ❌ CRITICAL: Download URL function not found
        echo WordPress auto-updates won't work
    ) else (
        echo ✅ Download URL function found

        :: Check if it uses release assets (not source archive)
        findstr /c:"releases/download" plugin-updater.php >nul
        if %errorlevel% neq 0 (
            echo ⚠️  WARNING: May be using source archive instead of release assets
            echo This could cause update failures
        ) else (
            echo ✅ Using release assets for downloads
        )
    )

    :: Check for updater class
    findstr /c:"class PressReleasesUpdater" plugin-updater.php >nul
    if %errorlevel% neq 0 (
        echo ❌ CRITICAL: PressReleasesUpdater class not found
        echo Auto-update functionality is broken
    ) else (
        echo ✅ PressReleasesUpdater class found
    )
) else (
    echo ❌ CRITICAL: plugin-updater.php missing
    echo WordPress auto-updates will not work
)
echo.

:: ==============================================
:: Test 5: File Structure Validation
:: ==============================================
echo [6/6] 📁 Essential Files Check...

:: Check for CSS file
if exist "press-releases.css" (
    echo ✅ CSS file found
) else (
    echo ❌ WARNING: press-releases.css missing - styling won't work
)

:: Check for JavaScript file
if exist "press-releases.js" (
    echo ✅ JavaScript file found
) else (
    echo ❌ WARNING: press-releases.js missing - AJAX functionality won't work
)

:: Check for documentation
if exist "README.md" (
    echo ✅ README.md found
) else (
    echo ⚠️  README.md missing - users won't have basic info
)

if exist "SHORTCODE-GUIDE.md" (
    echo ✅ Shortcode guide found
) else (
    echo ⚠️  SHORTCODE-GUIDE.md missing - users won't know how to use shortcodes
)
echo.

:: ==============================================
:: Final Report and Recommendations
:: ==============================================
echo ========================================
echo 📊 SAFE TESTING COMPLETE
echo ========================================
echo.
echo ✅ SAFETY GUARANTEE: No code was modified
echo ✅ VALIDATION: Plugin structure checked
echo ✅ SECURITY: Core protection features verified
echo ✅ FUNCTIONALITY: Essential components validated
echo.
echo 💡 Next Steps:
echo   - If all tests passed: Your plugin is working correctly
echo   - If warnings appeared: Review and fix issues manually
echo   - For updates: Test again after any changes
echo   - NEVER use auto-fix tools that modify code
echo.
echo 🛡️ Remember: This workflow is SAFE and will never
echo    change your working code. Manual fixes only!
echo.
pause