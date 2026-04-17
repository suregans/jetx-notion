# push-to-github.ps1
# Run this once from the Finalgit folder to push v3.0.0 to GitHub.
# Right-click → Run with PowerShell  OR  run in terminal:
#   cd "C:\Users\shadd\OneDrive\Desktop\EDRIVE\JETXMEDIA\PLUGINS\jetx-notion integration\Finalgit"
#   .\push-to-github.ps1
#
# When prompted for credentials:
#   Username: suregans
#   Password: [your GitHub Personal Access Token]
#   Generate one at: https://github.com/settings/tokens  (Scopes needed: repo)

$ErrorActionPreference = "Stop"

Write-Host "Initializing git repo..." -ForegroundColor Cyan
git init
git config user.name "Suregan Subramaniam"
git config user.email "jetxmediainc@gmail.com"

Write-Host "Staging all files..." -ForegroundColor Cyan
git add .

Write-Host "Committing..." -ForegroundColor Cyan
git commit -m "v3.0.0 - Multi-file architecture, Schema tab, white-label ready"

Write-Host "Setting remote..." -ForegroundColor Cyan
git remote add origin https://github.com/suregans/jetx-notion.git 2>$null
git remote set-url origin https://github.com/suregans/jetx-notion.git

Write-Host "Pushing to GitHub (you may be prompted for credentials)..." -ForegroundColor Cyan
git branch -M main
git push -u origin main --force

Write-Host ""
Write-Host "Done! View your repo at: https://github.com/suregans/jetx-notion" -ForegroundColor Green
