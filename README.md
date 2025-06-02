
# IPSymcon-Bewaesserung

**Bewässerung Multi-Zone**  
Ein IP-Symcon-Modul zur Steuerung von bis zu 10 Bewässerungssträngen (z.B. für Garten oder Landwirtschaft) – mit direkter KNX-Anbindung, Einzel- und Automatikbetrieb, Prioritäten, Laufzeiten und übersichtlicher Statusanzeige im WebFront.

---

## ✨ Features

- Bis zu **10 separat steuerbare Zonen/Stränge**
- **Manueller Modus** (Schalten EIN/AUS je Zone)
- **Automatik-Modus** mit:
  - Ablauf nach **Priorität** und **Dauer**
  - Nacheinander-Schaltung (immer nur ein Strang aktiv)
  - Übersichtliche Restlaufzeit- und Startzeitanzeige
- **Statusanzeige** je Zone:  
  Zeigt an, wann die nächste Bewässerung startet oder wie lange sie noch läuft
- **Kompatibel mit KNX** (direktes Schalten per verlinkter Bool-Variable)
- **Timer-gesteuert** (keine zyklischen Ereignisse notwendig)
- **WebFront-tauglich**

---

## 🛠️ Installation

1. Klone dieses Repository in deinen IP-Symcon-Module-Ordner:

   ```sh
   git clone https://github.com/AndreasWalder/IPSymcon-Bewaesserung.git
   ```

2. Starte den Symcon-Dienst neu.
3. Instanz anlegen:  
   *Objektbaum → Instanz hinzufügen → „Bewässerung Multi-Zone“*
4. KNX-Variablen zuordnen, Dauer und Priorität pro Zone einstellen.

---

## ⚙️ Konfiguration

Jede Zone (Strang) hat folgende Variablen:
- **Manuell** (`Bool`): Sofortiges EIN/AUS (übersteuert Automatik)
- **Automatik** (`Bool`): Automatik-Ablauf aktivieren/deaktivieren
- **Dauer** (`Int`): Bewässerungszeit in Sekunden
- **Priorität** (`Int`): Reihenfolge, in der die Stränge automatisch bewässert werden
- **Status** (`String`): „Läuft noch…“ oder „Start in…“

> Die zu schaltende KNX-Bool-Variable wird in der Instanzkonfiguration zugeordnet.

---

## 💡 Beispiel-Anwendungsfall

- Strang 1: Hecke Nordseite – Priorität 1, Dauer 1200 s
- Strang 2: Blumenbeet – Priorität 2, Dauer 900 s  
…usw.
- Alle 10 Stränge können unabhängig manuell oder automatisch bewässert werden.

---

## 🧑‍💻 Autor & Lizenz

- Erstellt von Andreas Walder
- MIT-Lizenz

---

## 🛠️ Weiterentwicklung

Gerne mit Forks, Issues & Pull Requests!
