# Operation12B-F44
Projekt1: FitFuerInfo 
- Kurs- & Raum-Verwaltungsssoftware
DIESE DATEI BEEINHALTET INIT & START/STOPP BEDIENUNGSANLEITUNG

FÜR AUSFÜHRLICHE SOFTWAREINFOS:             ./doc.pdf 
FÜR EINE DETAILIERTE BEDIENUNGSANLEITUNG:   ./Bedienungsanleitung.pdf

## Requirements: 
1. Installed XAMPP v.5.6.36 (Modules: Apache & MySQL)
**Download:** `sourceforge.net/projects/xampp/files/XAMPP Windows/5.6.36/`
→ Datei: `xampp-win32-5.6.36-0-VC11-installer.exe`
→ Installationspfad: U:/XAMPP_5.6.36
→ Bitte beachtet/erfüllt auch die XAMPP-spezifischen Requirements

## Initialisierung/Installation:
- git clone https://github.com/HartzZFear/Operation12B-F44.git
- ps .\Operation12B-F44\FitFuerInfo\init.ps1
(Startet MySQL und Apache, verschiebt die Dateien in die XAMPPOrdner und
führt einen SQL dump aus.)

## Bedienung: 
(nach erstmaliger initiierung wird der Apache und MySQL Server automatisch gestartet)
Starten: ps .\Operation12B-F44\FitFuerInfo\start.ps1
Stoppen: ps .\Operation12B-F44\FitFuerInfo\end.ps1