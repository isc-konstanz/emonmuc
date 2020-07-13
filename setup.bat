::BATCH file for windows
@echo off

:: BatchGotAdmin
:-------------------------------------
REM  --> Check for permissions
IF "%PROCESSOR_ARCHITECTURE%" EQU "amd64" (
>nul 2>&1 "%SYSTEMROOT%\SysWOW64\cacls.exe" "%SYSTEMROOT%\SysWOW64\config\system"
) ELSE (
>nul 2>&1 "%SYSTEMROOT%\system32\cacls.exe" "%SYSTEMROOT%\system32\config\system"
)

REM --> If error flag set, we do not have admin.
if '%errorlevel%' NEQ '0' (
    echo Requesting administrative privileges...
    goto UACPrompt
) else ( goto gotAdmin )

:UACPrompt
    echo Set UAC = CreateObject^("Shell.Application"^) > "%temp%\getadmin.vbs"
    set params= %*
    echo UAC.ShellExecute "cmd.exe", "/c ""%~s0"" %params:"=""%", "", "runas", 1 >> "%temp%\getadmin.vbs"

    "%temp%\getadmin.vbs"
    del "%temp%\getadmin.vbs"
    exit /B

:gotAdmin
    pushd "%CD%"
    CD /D "%~dp0"
:--------------------------------------

set ROOT_DIR=%~dp0
set BUNDLE_DIR=%ROOT_DIR%\lib\bundle
set BUNDLE_CONFIGS=%ROOT_DIR%\conf\bundle.d

if not exist "%BUNDLE_CONFIGS%\" (
    mkdir "%BUNDLE_CONFIGS%"
)
if not exist "%BUNDLE_CONFIGS%\openmuc-datalogger-emoncms.gradle" (
    mklink "%BUNDLE_CONFIGS%\openmuc-datalogger-emoncms.gradle" "%ROOT_DIR%\lib\bundle\openmuc-datalogger-emoncms.gradle"
)
if not exist "%BUNDLE_CONFIGS%\openmuc-server-restws.gradle" (
    mklink "%BUNDLE_CONFIGS%\openmuc-server-restws.gradle" "%ROOT_DIR%\lib\bundle\openmuc-server-restws.gradle"
)

set service=driver

:Arguments
if "%1" == "" goto Continue
    set bundle=%1
	
    if "%bundle%" == "--app" set service=app
    if "%bundle%" == "--datalogger" set service=datalogger
    if "%bundle%" == "--driver" set service=driver
    if "%bundle%" == "--server" set service=driver
	
    if /i "%bundle:~0,1%"=="-" (
        shift
        goto Arguments
    )
    mklink "%BUNDLE_CONFIGS%\openmuc-%service%-%bundle%.gradle" "%ROOT_DIR%\lib\bundle\openmuc-%service%-%bundle%.gradle"

shift
goto Arguments

:Continue

gradle -b "%ROOT_DIR%\build.gradle" bundles
