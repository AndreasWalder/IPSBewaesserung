# IPSymcon-Bewaesserung

**Bewässerung Multi-Zone**  
Ein modernes, flexibles IP-Symcon-Modul zur Steuerung von bis zu 10 Bewässerungssträngen (z. B. für Garten oder Landwirtschaft) – mit direkter KNX-Anbindung, Einzel- und Automatikbetrieb, Prioritäten, Laufzeiten, Pumpensteuerung und übersichtlicher Statusanzeige im WebFront.

---

## ✨ Features

- **Bis zu 10 separat steuerbare Zonen/Stränge**
    - Jede Zone frei benennbar (Konfig-Formular)
- **Manueller Modus:** Sofortiges EIN/AUS je Zone
- **Automatik-Modus:**  
    - Ablauf nach einstellbarer Priorität und Dauer  
    - Nacheinander-Schaltung (immer nur eine Prio-Gruppe läuft gleichzeitig)
- **Pumpensteuerung integriert:**
    - Wird im Automatikbetrieb automatisch mit eingeschaltet
    - Kann manuell zugeschaltet werden
    - Status- und Infoanzeige wie die Zonen
- **Statusanzeigen je Zone und Pumpe:**
    - Zeigt an, wann die nächste Bewässerung startet oder wie lange sie noch läuft
    - Fehler- und Warnmeldungen bei Problemen mit Aktoren
- **Kompatibel mit KNX** (direktes Schalten per verlinkter Bool-Variable)
- **Timer-gesteuert** (keine zyklischen Ereignisse notwendig)
- **WebFront-tauglich** – alle Variablen optimal beschriftet
- **Dynamische Instanzkonfiguration** – alles komfortabel über das Formular konfigurierbar

---

## 🛠️ Installation

1. **Repository klonen** in deinen IP-Symcon-Module-Ordner (z. B. `/var/lib/symcon/modules/`):

   ```
   git clone https://github.com/AndreasWalder/IPSBewaesserung
   ```

2. **Symcon-Dienst neu starten**.

3. **Instanz anlegen:**  
   Objektbaum → Instanz hinzufügen → „Bewässerung Multi-Zone“

4. **Zonenanzahl, Namen, Aktoren und Pumpe in der Instanzkonfiguration festlegen**.

---

## ⚙️ Konfiguration

Jede Zone (Strang) bietet folgende Einstellungen und Variablen:

- **Name** (Text): Frei wählbar (z. B. „Rasen Ost“)
- **Manuell** (Bool): Sofortiges EIN/AUS (übersteuert Automatik)
- **Automatik** (Bool): Automatik-Ablauf aktivieren/deaktivieren
- **Dauer** (Int): Bewässerungszeit in Sekunden
- **Priorität** (Int): Reihenfolge der Bewässerung
- **Status** (Bool): Ein/Aus-Status (schaltbar)
- **Info** (String): Zeigt Restlaufzeit, Fehler oder Startzeit an

**Pumpe:**  
- **Pumpe Manuell** (Bool): Manuelle Zuschaltung der Pumpe
- **Pumpe Status** (Bool): EIN/AUS-Status der Pumpe (automatisch und manuell)
- **Pumpe Info** (String): Statusmeldung oder Fehler

Die zu schaltenden KNX-Bool-Variablen werden in der Instanzkonfiguration zugeordnet.

---

## 💡 Beispiel-Anwendungsfall

- **Zone 1:** „Hecke Nordseite“ – Priorität 1, Dauer 1200 s
- **Zone 2:** „Blumenbeet“ – Priorität 2, Dauer 900 s  
- **Zone 3:** „Rasen Ost“ – Priorität 3, Dauer 1800 s  
- **Pumpe:** Wird automatisch immer dann geschaltet, wenn eine Zone läuft, oder kann manuell zugeschaltet werden.

Alle Zonen können unabhängig manuell oder automatisch bewässert werden.

---

## 🧑‍💻 Autor & Lizenz

Erstellt von Andreas Walder  
MIT-Lizenz (`LICENSE` liegt bei)

---

## 🛠️ Weiterentwicklung

Mit Forks, Issues & Pull Requests sehr gerne gesehen!  
Fehler, Ideen und Verbesserungen bitte als GitHub Issue oder PR einreichen.

---

**Letztes Update:** 2025-06-03 – Pumpensteuerung, flexible Namen, Fehler-Handling, neue Projektstruktur.
