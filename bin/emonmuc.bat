::BATCH file for windows
@echo off
set SCRIPT_DIR=%~dp0
cd %SCRIPT_DIR%..

@echo on
java -jar bin\felix.jar
