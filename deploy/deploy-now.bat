@echo off
REM deploy-now.bat - Run from CMD on Windows after `git push`.
REM
REM Pulls latest main on hexugo.com, rebuilds, reloads, restarts workers.
REM Works for any user — no ~/.ssh/config alias required.
REM
REM First-time setup:
REM   1. Get private key file `hexugo_deploy` from team password vault.
REM   2. Place at:  %USERPROFILE%\.ssh\hexugo_deploy
REM   3. Run:        deploy\deploy-now.bat
REM
REM Usage:
REM   deploy\deploy-now.bat              (deploys main)
REM   set BRANCH=hotfix/x ^&^& deploy\deploy-now.bat
REM
setlocal enabledelayedexpansion

if "%BRANCH%"=="" set BRANCH=main
if "%KEY%"=="" set KEY=%USERPROFILE%\.ssh\hexugo_deploy

if not exist "%KEY%" (
    echo.
    echo ERROR: SSH key not found at: %KEY%
    echo Get 'hexugo_deploy' from the team vault and put it there.
    echo Or run:  set KEY=C:\path\to\hexugo_deploy ^&^& deploy\deploy-now.bat
    echo.
    exit /b 1
)

echo.
echo ^>^> Deploying %BRANCH% to deploy@47.86.173.194:2222
echo.

ssh -t ^
    -i "%KEY%" ^
    -p 2222 ^
    -o StrictHostKeyChecking=accept-new ^
    -o ServerAliveInterval=30 ^
    deploy@47.86.173.194 ^
    "BRANCH=%BRANCH% bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"

endlocal
