# ================================
# FitFuerInfo – STOP Script
# ================================

Write-Host "=== FitFuerInfo wird gestoppt ==="

# --- XAMPP Pfad ---
$XamppRoot = "U:\XAMPP_5.6.36"

# --- Prüfen ob XAMPP existiert ---
if (!(Test-Path $XamppRoot)) {
    Write-Host "FEHLER: XAMPP wurde nicht gefunden unter $XamppRoot"
    exit 1
}

# --- Apache stoppen ---
Write-Host "Stoppe Apache..."
Start-Process "$XamppRoot\apache_stop.bat"

# --- MySQL stoppen ---
Write-Host "Stoppe MySQL..."
Start-Process "$XamppRoot\mysql_stop.bat"

Write-Host "=== Server gestoppt ==="
