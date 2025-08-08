<?php
class BewaesserungCore extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Manueller Schritt
        $this->RegisterVariableBoolean("ManualNextStep", ">> Manueller Schritt", "~Switch", 8000);
        $this->EnableAction("ManualNextStep");

        // Basis-Properties
        $this->RegisterPropertyInteger("ZoneCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyString("ZoneName$i", "Zone $i");
            $this->RegisterPropertyInteger("AktorID$i", 0);
            // Alte Properties für Dauer in Minuten bleiben erhalten (werden aber NICHT mehr für Reset verwendet).
            $this->RegisterPropertyInteger("Dauer$i", 5);
        }
        // Nebenstelle
        $this->RegisterPropertyInteger("AktorID11", 0);
        $this->RegisterPropertyInteger("Dauer11", 5);

        // Pumpe
        $this->RegisterPropertyInteger("PumpeAktorID", 0);
        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");
        $this->RegisterVariableBoolean("PumpeManuell", "Pumpe Manuell", "~Switch", 950);
        $this->EnableAction("PumpeManuell");
        $this->RegisterVariableBoolean("PumpeStatus", "Pumpe Status", "~Switch", 951);
        $this->RegisterVariableString("PumpeInfo", "Pumpe Info", "", 952);

        // Nebenstelle-Variablen
        $nebenName = "Nebenstelle";
        $this->RegisterVariableBoolean("Manuell11", "Manuell $nebenName", "~Switch", 1110);
        $this->EnableAction("Manuell11");
        $this->RegisterVariableBoolean("Automatik11", "Automatik $nebenName", "~Switch", 1111);
        $this->EnableAction("Automatik11");
        $this->RegisterVariableInteger("Dauer11", "Dauer $nebenName", "IPSBW.DurationMin", 1112);
        $this->EnableAction("Dauer11");
        $this->RegisterVariableInteger("Prio11", "Priorität $nebenName", "IPSBW.Prioritaet", 1113);
        $this->EnableAction("Prio11");
        $this->RegisterVariableBoolean("Status11", "Status $nebenName (EIN/AUS)", "~Switch", 1114);
        $this->RegisterVariableString("Info11", "Info $nebenName", "", 1115);

        // Attribute für Prio-Starts
        for ($p = 0; $p <= 99; $p++) {
            $this->RegisterAttributeInteger("StartPrio$p", 0);
        }

        // Flags für manuellen Schritt
        $this->RegisterAttributeBoolean("ManualStepActive", false);
        $this->RegisterAttributeInteger("ManualStepNextPrio", 0);

        // Profile
        if (!IPS_VariableProfileExists("IPSBW.DurationMin")) {
            IPS_CreateVariableProfile("IPSBW.DurationMin", 1);
            IPS_SetVariableProfileText("IPSBW.DurationMin", "", " min");
            IPS_SetVariableProfileDigits("IPSBW.DurationMin", 0);
            IPS_SetVariableProfileValues("IPSBW.DurationMin", 1, 240, 1);
        }

        if (!IPS_VariableProfileExists("IPSBW.Prioritaet")) {
            IPS_CreateVariableProfile("IPSBW.Prioritaet", 1);
            IPS_SetVariableProfileValues("IPSBW.Prioritaet", 1, 20, 1);
        }

        // Timer (immer in Create registrieren)
        $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        if ($zoneCount < 1) $zoneCount = 1;
        if ($zoneCount > 10) $zoneCount = 10;

        // Zonen anlegen
        for ($i = 1; $i <= $zoneCount; $i++) {
            $zoneName = $this->ReadPropertyString("ZoneName$i");

            $this->RegisterVariableBoolean("Manuell$i", "Manuell $zoneName", "~Switch", 1000 + $i * 10);
            $this->EnableAction("Manuell$i");

            $this->RegisterVariableBoolean("Automatik$i", "Automatik $zoneName", "~Switch", 1001 + $i * 10);
            $this->EnableAction("Automatik$i");

            // Laufzeit (dynamisch im WebFront editierbar, wird zur Restzeit verändert)
            $this->RegisterVariableInteger("Dauer$i", "Dauer $zoneName", "IPSBW.DurationMin", 1002 + $i * 10);
            $this->EnableAction("Dauer$i");

            // Startlaufzeit (nur als Ausgangswert bei Start der Automatik)
            $this->RegisterVariableInteger("DauerStart$i", "DauerStart $zoneName", "IPSBW.DurationMin", 1002 + $i * 10 + 1);
            if (GetValue($this->GetIDForIdent("DauerStart$i")) == 0) {
                // Mit aktuellem Dauer-Wert initialisieren (oder Fallback 5)
                $cur = @GetValue($this->GetIDForIdent("Dauer$i"));
                if ($cur <= 0) $cur = 5;
                SetValueInteger($this->GetIDForIdent("DauerStart$i"), $cur);
            }

            $this->RegisterVariableInteger("Prio$i", "Priorität $zoneName", "IPSBW.Prioritaet", 1003 + $i * 10);
            $this->EnableAction("Prio$i");

            $this->RegisterVariableBoolean("Status$i", "Status $zoneName (EIN/AUS)", "~Switch", 1004 + $i * 10);
            $this->RegisterVariableString("Info$i", "Info $zoneName", "", 1005 + $i * 10);

            IPS_SetName($this->GetIDForIdent("Manuell$i"), "Manuell $zoneName");
            IPS_SetName($this->GetIDForIdent("Automatik$i"), "Automatik $zoneName");
            IPS_SetName($this->GetIDForIdent("Dauer$i"), "Dauer $zoneName");
            IPS_SetName($this->GetIDForIdent("DauerStart$i"), "DauerStart $zoneName");
            IPS_SetName($this->GetIDForIdent("Prio$i"), "Priorität $zoneName");
            IPS_SetName($this->GetIDForIdent("Status$i"), "Status $zoneName (EIN/AUS)");
            IPS_SetName($this->GetIDForIdent("Info$i"), "Info $zoneName");

            // Hinweis, wenn kein Aktor verknüpft ist
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $infoID = $this->GetIDForIdent("Info$i");
            if ($aktorID == 0) {
                SetValueString($infoID, "Bitte KNX-Aktor für $zoneName auswählen!");
            }
        }

        // Nebenstelle (Zone 11)
        $nebenName = "Nebenstelle";
        $this->RegisterVariableBoolean("Manuell11", "Manuell $nebenName", "~Switch", 1110);
        $this->EnableAction("Manuell11");
        $this->RegisterVariableBoolean("Automatik11", "Automatik $nebenName", "~Switch", 1111);
        $this->EnableAction("Automatik11");

        $this->RegisterVariableInteger("Dauer11", "Dauer $nebenName", "IPSBW.DurationMin", 1112);
        $this->EnableAction("Dauer11");
        $this->RegisterVariableInteger("DauerStart11", "DauerStart $nebenName", "IPSBW.DurationMin", 1112 + 1);
        if (GetValue($this->GetIDForIdent("DauerStart11")) == 0) {
            $cur = @GetValue($this->GetIDForIdent("Dauer11"));
            if ($cur <= 0) $cur = 5;
            SetValueInteger($this->GetIDForIdent("DauerStart11"), $cur);
        }

        $this->RegisterVariableInteger("Prio11", "Priorität $nebenName", "IPSBW.Prioritaet", 1113);
        $this->EnableAction("Prio11");
        $this->RegisterVariableBoolean("Status11", "Status $nebenName (EIN/AUS)", "~Switch", 1114);
        $this->RegisterVariableString("Info11", "Info $nebenName", "", 1115);

        IPS_SetName($this->GetIDForIdent("Manuell11"), "Manuell $nebenName");
        IPS_SetName($this->GetIDForIdent("Automatik11"), "Automatik $nebenName");
        IPS_SetName($this->GetIDForIdent("Dauer11"), "Dauer $nebenName");
        IPS_SetName($this->GetIDForIdent("DauerStart11"), "DauerStart $nebenName");
        IPS_SetName($this->GetIDForIdent("Prio11"), "Priorität $nebenName");
        IPS_SetName($this->GetIDForIdent("Status11"), "Status $nebenName (EIN/AUS)");
        IPS_SetName($this->GetIDForIdent("Info11"), "Info $nebenName");

        // Pumpe-Benennungen
        IPS_SetName($this->GetIDForIdent("PumpeManuell"), "Pumpe Manuell");
        IPS_SetName($this->GetIDForIdent("PumpeStatus"), "Pumpe Status");
        IPS_SetName($this->GetIDForIdent("PumpeInfo"), "Pumpe Info");

        // Alle Prio-Starts zurücksetzen
        $this->ResetAllPrioStarts();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == "GesamtAutomatik") {
            if ($Value) {
                $this->ResetAllPrioStarts();
                $this->ResetAllStatusAndInfosFromStart(); // <- NEU: Dauer = DauerStart übernehmen
                IPS_LogMessage("BWZ", "GesamtAutomatik: Status/Info zurückgesetzt & Dauer aus DauerStart übernommen.");
            }
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->Evaluate();
            return;
        }

        if ($Ident == "PumpeManuell") {
            SetValue($this->GetIDForIdent("PumpeManuell"), $Value);
            $this->Evaluate();
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            if ($Ident == "Manuell$i" || $Ident == "Automatik$i" || $Ident == "Dauer$i" || $Ident == "Prio$i") {
                SetValue($this->GetIDForIdent($Ident), $Value);
                $this->Evaluate();
                return;
            }
        }

        if ($Ident == "Manuell11" || $Ident == "Automatik11" || $Ident == "Dauer11" || $Ident == "Prio11") {
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->Evaluate();
            return;
        }

        if ($Ident == "ManualNextStep") {
            if ($Value) {
                $this->ManualStepAdvance();
                $this->SetValue("ManualNextStep", false);
            }
            return;
        }

        if ($Ident == "ResetAll") {
            $this->ResetAllPrioStarts();
            $this->Evaluate();
            return;
        }

        if ($Ident == "RestartTimer") {
            IPS_LogMessage("BWZ-Timer", "EvaluateTimer wurde auf Intervall 1000 gesetzt");
            return;
        }

        if ($Ident == "Evaluate") {
            $this->Evaluate();
            return;
        }

        SetValue($this->GetIDForIdent($Ident), $Value);
    }

    public function Evaluate()
    {
        $now = time();
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        if ($zoneCount < 1) $zoneCount = 1;
        if ($zoneCount > 10) $zoneCount = 10;

        $gesamtAuto = GetValue($this->GetIDForIdent("GesamtAutomatik"));

        // Pumpe
        if ($gesamtAuto) {
            SetValue($this->GetIDForIdent("PumpeStatus"), true);
            SetValue($this->GetIDForIdent("PumpeInfo"), "Automatik aktiv");
        } else {
            $manPumpe = GetValue($this->GetIDForIdent("PumpeManuell"));
            SetValue($this->GetIDForIdent("PumpeStatus"), $manPumpe);
            SetValue($this->GetIDForIdent("PumpeInfo"), $manPumpe ? "Manuell an" : "Manuell aus");
        }

        // Pumpe Aktor ansteuern
        $pumpeAktorID = $this->ReadPropertyInteger("PumpeAktorID");
        $pumpeStatus = GetValue($this->GetIDForIdent("PumpeStatus"));
        if ($pumpeAktorID > 0 && @IPS_ObjectExists($pumpeAktorID)) {
            @RequestAction($pumpeAktorID, $pumpeStatus);
        }

        if ($gesamtAuto) {
            // Automatik nach Prio
            $prioMap = [];
            for ($i = 1; $i <= $zoneCount + 1; $i++) {
                $auto = @GetValue($this->GetIDForIdent("Automatik$i"));
                $prio = @GetValue($this->GetIDForIdent("Prio$i"));
                $dauerMin = @GetValue($this->GetIDForIdent("Dauer$i")); // Minuten aus der Laufzeit-Variable
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                $statusID = $this->GetIDForIdent("Status$i");
                $infoID = $this->GetIDForIdent("Info$i");

                if ($auto && $aktorID > 0 && @IPS_ObjectExists($aktorID)) {
                    if (!isset($prioMap[$prio])) $prioMap[$prio] = [];
                    $prioMap[$prio][] = [
                        'index' => $i,
                        'dauer' => max(0, (int)$dauerMin) * 60, // in Sekunden
                        'aktorID' => $aktorID,
                        'statusID' => $statusID,
                        'infoID' => $infoID
                    ];
                }
            }
            if (empty($prioMap)) {
                $this->ResetAllPrioStarts();
                return;
            }

            ksort($prioMap, SORT_NUMERIC);
            $globalOffset = 0;
            foreach ($prioMap as $prio => $zoneArray) {
                $startAttr = "StartPrio" . $prio;
                $prioDauer = $this->getPrioDauer($zoneArray);
                $startPrio = $this->ReadAttributeInteger($startAttr);

                if ($startPrio === -1) {
                    foreach ($zoneArray as $z) {
                        SetValueBoolean($z['statusID'], false);
                        SetValueString($z['infoID'], "Automatik für Prio $prio erledigt");
                    }
                    continue;
                }

                $manualNextPrio = $this->ReadAttributeInteger("ManualStepNextPrio");
                if ($startPrio <= 0) {
                    if ($manualNextPrio == $prio) {
                        $startPrio = $now; // sofort
                        $this->WriteAttributeInteger($startAttr, $startPrio);
                        $this->WriteAttributeInteger("ManualStepNextPrio", 0);
                    } else {
                        $startPrio = $now + $globalOffset;
                        $this->WriteAttributeInteger($startAttr, $startPrio);
                    }
                }

                if ($now > $startPrio + $prioDauer) {
                    $this->WriteAttributeInteger($startAttr, -1);
                    foreach ($zoneArray as $z) {
                        SetValueBoolean($z['statusID'], false);
                        SetValueString($z['infoID'], "Automatik für Prio $prio erledigt");
                    }
                    continue;
                }

                $maxDauer = 0;
                foreach ($zoneArray as $z) {
                    $ende = $startPrio + $z['dauer'];
                    if ($z['dauer'] > $maxDauer) $maxDauer = $z['dauer'];
                    $nowActive = ($now >= $startPrio && $now < $ende);
                    if ($nowActive) {
                        if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                            $this->SafeRequestAction($z['aktorID'], true, $z['statusID'], $z['infoID'], "Automatik läuft");
                        }
                        $rest = $ende - $now;
                        $restMin = ceil($rest / 60);
                        SetValueString($z['infoID'], "Automatik läuft noch $restMin min (Prio $prio)");
                    } else {
                        if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                            $this->SafeRequestAction($z['aktorID'], false, $z['statusID'], $z['infoID'], "");
                        }
                        $wait = $startPrio - $now;
                        $waitMin = ($wait > 0) ? ceil($wait / 60) : 0;
                        SetValueString($z['infoID'], "Automatik Start in $waitMin min (Prio $prio)");
                    }
                }
                $globalOffset += $maxDauer;
            }

            // Läuft noch was?
            $irgendetwasAktiv = false;
            foreach ($prioMap as $prio => $zoneArray) {
                $startAttr = "StartPrio" . $prio;
                $startPrio = $this->ReadAttributeInteger($startAttr);
                if ($startPrio === -1) continue;
                foreach ($zoneArray as $z) {
                    $ende = $startPrio + $z['dauer'];
                    if ($now >= $startPrio && $now < $ende) {
                        $irgendetwasAktiv = true;
                        break 2;
                    }
                }
            }
            // Gibt es noch offene Gruppen?
            $priosOffen = 0;
            foreach ($prioMap as $prio => $zoneArray) {
                $startAttr = "StartPrio" . $prio;
                $startPrio = $this->ReadAttributeInteger($startAttr);
                if ($startPrio !== -1) {
                    $priosOffen++;
                }
            }

            if (!$irgendetwasAktiv && $priosOffen == 0) {
                if ($this->ReadAttributeBoolean("ManualStepActive")) {
                    $this->WriteAttributeBoolean("ManualStepActive", false);
                    IPS_LogMessage("BWZ", "Automatik bleibt nach manuellem Schritt aktiv!");
                } else {
                    SetValue($this->GetIDForIdent("GesamtAutomatik"), false);
                }
            }
            return;
        }

        // Manuellbetrieb (Automatik aus)
        for ($i = 1; $i <= $zoneCount; $i++) {
            $manuell = GetValue($this->GetIDForIdent("Manuell$i"));
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $statusID = $this->GetIDForIdent("Status$i");
            $infoID = $this->GetIDForIdent("Info$i");

            if ($aktorID > 0 && @IPS_ObjectExists($aktorID)) {
                if ($manuell) {
                    $this->SafeRequestAction($aktorID, true, $statusID, $infoID, "Manuell eingeschaltet");
                } else {
                    $this->SafeRequestAction($aktorID, false, $statusID, $infoID, "Manuell ausgeschaltet");
                }
            } else {
                SetValueBoolean($statusID, false);
                SetValueString($infoID, "Keine AktorID");
            }
        }

        // Nebenstelle (Zone 11) im Manuellbetrieb
        $manuell = GetValue($this->GetIDForIdent("Manuell11"));
        $aktorID = $this->ReadPropertyInteger("AktorID11");
        $statusID = $this->GetIDForIdent("Status11");
        $infoID = $this->GetIDForIdent("Info11");
        if ($aktorID > 0 && @IPS_ObjectExists($aktorID)) {
            if ($manuell) {
                $this->SafeRequestAction($aktorID, true, $statusID, $infoID, "Manuell eingeschaltet");
            } else {
                $this->SafeRequestAction($aktorID, false, $statusID, $infoID, "Manuell ausgeschaltet");
            }
        } else {
            SetValueBoolean($statusID, false);
            SetValueString($infoID, "Keine AktorID");
        }
    }

    private function ResetAllStatusAndInfosFromStart()
    {
        // Dauer = DauerStart übernehmen, Status/Info zurücksetzen
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        for ($i = 1; $i <= $zoneCount; $i++) {
            $statusID = $this->GetIDForIdent("Status$i");
            if (@IPS_VariableExists($statusID)) {
                SetValueBoolean($statusID, false);
            }
            $infoID = $this->GetIDForIdent("Info$i");
            if (@IPS_VariableExists($infoID)) {
                SetValueString($infoID, "");
            }
            $dauerID = $this->GetIDForIdent("Dauer$i");
            $dauerStartID = $this->GetIDForIdent("DauerStart$i");
            if (@IPS_VariableExists($dauerID) && @IPS_VariableExists($dauerStartID)) {
                $start = GetValueInteger($dauerStartID);
                if ($start <= 0) $start = 5;
                SetValueInteger($dauerID, $start);
            }
        }
        // Nebenstelle
        $statusID = $this->GetIDForIdent("Status11");
        $infoID   = $this->GetIDForIdent("Info11");
        if (@IPS_VariableExists($statusID)) SetValueBoolean($statusID, false);
        if (@IPS_VariableExists($infoID))   SetValueString($infoID, "");

        $dauerID = $this->GetIDForIdent("Dauer11");
        $dauerStartID = $this->GetIDForIdent("DauerStart11");
        if (@IPS_VariableExists($dauerID) && @IPS_VariableExists($dauerStartID)) {
            $start = GetValueInteger($dauerStartID);
            if ($start <= 0) $start = 5;
            SetValueInteger($dauerID, $start);
        }
    }

    private function ManualStepAdvance()
    {
        $this->WriteAttributeBoolean("ManualStepActive", true);

        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        $foundPrio = null;

        // Aktive Prio finden und Restzeit auf 0
        for ($prio = 1; $prio <= 99; $prio++) {
            $found = false;
            for ($i = 1; $i <= $zoneCount; $i++) {
                $prioVar = $this->GetIDForIdent("Prio$i");
                $zonePrio = @GetValue($prioVar);
                $statusID = $this->GetIDForIdent("Status$i");
                $dauerID  = $this->GetIDForIdent("Dauer$i");
                if (@IPS_VariableExists($statusID) && $zonePrio == $prio) {
                    $status = GetValueBoolean($statusID);
                    if ($status) {
                        if (@IPS_VariableExists($dauerID)) {
                            SetValueInteger($dauerID, 0);
                            IPS_LogMessage("BWZ", "Restlaufzeit Zone $i (Prio $prio) auf 0 gesetzt (ID $dauerID)");
                            $found = true;
                            $foundPrio = $prio;
                        }
                    }
                }
            }
            if ($found) break;
        }

        // Nächste offene Prio sofort starten
        if ($foundPrio !== null) {
            for ($nextPrio = $foundPrio + 1; $nextPrio <= 99; $nextPrio++) {
                $startAttr = "StartPrio" . $nextPrio;
                $startPrio = $this->ReadAttributeInteger($startAttr);
                if ($startPrio !== -1) {
                    $this->WriteAttributeInteger($startAttr, time());
                    IPS_LogMessage("BWZ", "StartPrio$nextPrio auf jetzt gesetzt durch manuellen Schritt.");
                    $this->WriteAttributeInteger("ManualStepNextPrio", $nextPrio);
                    break;
                }
            }
        }
    }
}
?>
