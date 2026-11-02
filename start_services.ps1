# FabricSense - Start All Services
# This script starts both the Fabric Consumption and Fabric Identification APIs

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "    FabricSense - Starting All Services" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Resolve Python executable
$venvPython = Join-Path $PWD ".venv\Scripts\python.exe"
if (Test-Path $venvPython) {
    $pythonExe = $venvPython
    Write-Host "Using virtual environment Python: $pythonExe" -ForegroundColor Green
} else {
    $pythonExe = "python"
    Write-Host "No virtual environment Python found. Falling back to system python." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Starting services..." -ForegroundColor Cyan
Write-Host ""

function Wait-ForApi {
    param(
        [string]$Url,
        [string]$Name,
        [int]$TimeoutSeconds = 90
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
                Write-Host "   $Name is reachable at $Url" -ForegroundColor Green
                return $true
            }
        } catch {
            Start-Sleep -Seconds 2
        }
    }

    Write-Host "   $Name did not become reachable within $TimeoutSeconds seconds." -ForegroundColor Red
    return $false
}

# Start Fabric Consumption API (Port 5000) in background
Write-Host "1. Starting Fabric Consumption API on port 5000..." -ForegroundColor Yellow
$consumptionProcess = Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD'; & '$pythonExe' app.py" -PassThru

# Wait a moment
Start-Sleep -Seconds 2

# Start Fabric Identification API (Port 5001) in background
Write-Host "2. Starting Fabric Identification API on port 5001..." -ForegroundColor Yellow
$identificationProcess = Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PWD'; & '$pythonExe' fabric_identification_api.py" -PassThru

Write-Host ""
Write-Host "Checking service health..." -ForegroundColor Cyan

$isConsumptionUp = Wait-ForApi -Url "http://127.0.0.1:5000/" -Name "Fabric Consumption API"
$isIdentificationUp = Wait-ForApi -Url "http://127.0.0.1:5001/" -Name "Fabric Identification API"

if (-not ($isConsumptionUp -and $isIdentificationUp)) {
    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Red
    Write-Host "    One or more services failed to start" -ForegroundColor Red
    Write-Host "============================================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "Process IDs:" -ForegroundColor Yellow
    Write-Host "  Consumption API PID:    $($consumptionProcess.Id)" -ForegroundColor White
    Write-Host "  Identification API PID: $($identificationProcess.Id)" -ForegroundColor White
    Write-Host ""
    Write-Host "If a window shows import errors, install dependencies with:" -ForegroundColor Yellow
    Write-Host "  .\.venv\Scripts\python.exe -m pip install -r requirements.txt" -ForegroundColor White
    Write-Host ""
    Write-Host "Press any key to exit..." -ForegroundColor Gray
    $null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
    exit 1
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "    All Services Started!" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Services running:" -ForegroundColor Cyan
Write-Host "  Fabric Consumption API:    http://127.0.0.1:5000" -ForegroundColor White
Write-Host "  Fabric Identification API: http://127.0.0.1:5001" -ForegroundColor White
Write-Host ""
Write-Host "Access the application:" -ForegroundColor Cyan
Write-Host "  Homepage: file:///C:/xampp/htdocs/FabricSense/loginHomePage.html" -ForegroundColor White
Write-Host ""
Write-Host "Keep all terminal windows open while using the application!" -ForegroundColor Yellow
Write-Host ""
Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
