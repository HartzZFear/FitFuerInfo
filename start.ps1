# ================================
# FitFuerInfo – START Script
# ================================

Write-Host "=== FitFuerInfo wird gestartet ==="

# --- XAMPP Pfad ---
$XamppRoot = "U:\XAMPP_5.6.36"

# --- Prüfen ob XAMPP existiert ---
if (!(Test-Path $XamppRoot)) {
    Write-Host "FEHLER: XAMPP wurde nicht gefunden unter $XamppRoot"
    exit 1
}

# --- Apache starten ---
Write-Host "Starte Apache..."
Start-Process "$XamppRoot\apache_start.bat"

# --- MySQL starten ---
Write-Host "Starte MySQL..."
Start-Process "$XamppRoot\mysql_start.bat"

Write-Host "=== Server gestartet ==="
Write-Host "Projekt erreichbar unter: http://localhost/Operation12B-F44/FitFuerInfo"
