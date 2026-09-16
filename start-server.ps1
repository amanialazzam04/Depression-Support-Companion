# Run from PowerShell: .\start-server.ps1
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location (Join-Path $here "server")
if (-not (Test-Path "node_modules")) {
  npm install
}
npm start
