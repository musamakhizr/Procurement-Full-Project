# deploy-now.ps1 — Run from PowerShell on Windows after `git push`.
#
# Pulls latest main on hexugo.com, rebuilds, reloads, restarts workers.
# Works for any user — no ~/.ssh/config alias required.
#
# First-time setup:
#   1. Get private key file `hexugo_deploy` from team password vault.
#   2. Place at:  $env:USERPROFILE\.ssh\hexugo_deploy
#   3. Run:        .\deploy\deploy-now.ps1
#
# Usage:
#   .\deploy\deploy-now.ps1                                # deploys main
#   .\deploy\deploy-now.ps1 -Branch hotfix/x               # different branch
#   .\deploy\deploy-now.ps1 -Key C:\keys\my-key            # different key
#
param(
    [string]$Branch = "main",
    [string]$Key    = "$env:USERPROFILE\.ssh\hexugo_deploy",
    [string]$Host_  = "47.86.173.194",
    [int]   $Port   = 2222,
    [string]$User   = "deploy"
)

if (-not (Test-Path $Key)) {
    Write-Host ""
    Write-Host "ERROR: SSH key not found at: $Key" -ForegroundColor Red
    Write-Host "Get 'hexugo_deploy' from the team vault and put it there." -ForegroundColor Yellow
    Write-Host "Or run: .\deploy\deploy-now.ps1 -Key C:\path\to\hexugo_deploy" -ForegroundColor Yellow
    Write-Host ""
    exit 1
}

Write-Host ""
Write-Host "▶ Deploying $Branch to $User@${Host_}:$Port" -ForegroundColor Cyan
Write-Host ""

ssh -t `
    -i $Key `
    -p $Port `
    -o StrictHostKeyChecking=accept-new `
    -o ServerAliveInterval=30 `
    "$User@$Host_" `
    "BRANCH=$Branch bash /opt/hexugo-deploy/deploy/scripts/quick-deploy.sh"
