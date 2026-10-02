# ================================
# FitFuerInfo – INIT Script
# ================================

Write-Host "=== FitFuerInfo Initialisierung wird gestartet ==="

# --- Repo Root ermitteln ---
$RepoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path

# --- XAMPP Pfade ---
$XamppRoot = "U:\XAMPP_5.6.36"
$HtdocsTarget = "$XamppRoot\htdocs\Operation12B-F44\FitFuerInfo"
$MysqlBin = "$XamppRoot\mysql\bin\mysql.exe"

# --- Projektpfade ---
$SourceWeb = "$RepoRoot\web"
$SqlDump = "$RepoRoot\db\ffi_dbinit.sql"

# --- Prüfen ob XAMPP existiert ---
if (!(Test-Path $XamppRoot)) {
    Write-Host "FEHLER: XAMPP wurde nicht gefunden unter $XamppRoot, Version prüfen!"
    exit 1
}

# --- Webdateien kopieren ---
Write-Host "Kopiere PHP-Dateien nach htdocs..."
if (!(Test-Path $HtdocsTarget)) { New-Item -ItemType Directory -Path $HtdocsTarget | Out-Null }
Copy-Item -Path "$SourceWeb\*" -Destination $HtdocsTarget -Recurse -Force

# --- Apache starten ---
Write-Host "Starte Apache..."
Start-Process "$XamppRoot\apache_start.bat"

# --- MySQL starten ---
Write-Host "Starte MySQL..."
Start-Process "$XamppRoot\mysql_start.bat"

# --- Warten bis MySQL bereit ist ---
Write-Host "Warte auf MySQL..."
Start-Sleep -Seconds 5

# --- SQL Dump importieren ---
if (Test-Path $SqlDump) {
    Write-Host "Importiere SQL-Datenbank..."
    & $MysqlBin -u root < $SqlDump
    Write-Host "SQL-Import abgeschlossen."
} else {
    Write-Host "WARNUNG: Kein SQL-Dump gefunden unter $SqlDump"
}

# --- Cleanup: Repo leeren ---
Write-Host "Bereinige Repo (entferne web/ und db/)..."

$FoldersToRemove = @("$RepoRoot\web", "$RepoRoot\db")

foreach ($folder in $FoldersToRemove) {
    if (Test-Path $folder) {
        Remove-Item -Path $folder -Recurse -Force
        Write-Host "Ordner entfernt: $folder"
    }
}

Write-Host "=== Initialisierung abgeschlossen ==="
Write-Host "Projekt erreichbar unter: http://localhost/Operation12B-F44/FitFuerInfo"