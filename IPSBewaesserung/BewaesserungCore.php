<?php
class BewaesserungCore extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterVariableBoolean("ManualNextStep", ">> Manueller Schritt", "~Switch", 8000);
        $this->EnableAction("ManualNextStep");
        $this->RegisterPropertyInteger("ZoneCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyString("ZoneName$i", "Zone $i");
            $this->RegisterPropertyInteger("AktorID$i", 0);
        }
        // Nebenstelle
        $this->RegisterPropertyInteger("AktorID11", 0);

        $this->RegisterPropertyInteger("PumpeAktorID", 0);

        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        // Pumpe
        $this->RegisterVariableBoolean("PumpeManuell", "Pumpe Manuell", "~Switch", 950);
        $this->EnableAction("PumpeManuell");
        $this->RegisterVariableBoolean("PumpeStatus", "Pumpe Status", "~Switch", 951);
        $this->RegisterVariableString("PumpeInfo", "Pumpe Info", "", 952);

        // Nebenstelle (11. Zone)
        $nebenName = "Nebenstelle";
        $this->RegisterVariableBoolean("Manuell11", "Manuell $nebenName", "~Switch", 1110);
        $this->EnableAction("Manuell11");
        $this->RegisterVariableBoolean("Automatik11", "Automatik $nebenName", "~Switch", 1111);
        $this->EnableAction("Automatik11");
        $this->RegisterVariableInteger("Dauer11", "Dauer $nebenName", "IPSBW.Duration", 1112);
        $this->EnableAction("Dauer11");
        $this->RegisterVariableInteger("Prio11", "Priorität $nebenName", "IPSBW.Prioritaet", 1113);
        $this->EnableAction("Prio11");
        $this->RegisterVariableBoolean("Status11", "Status $nebenName (EIN/AUS)", "~Switch", 1114);
        $this->RegisterVariableString("Info11", "Info $nebenName", "", 1115);

        // Prio-Startzeiten
        for ($p = 0; $p <= 99; $p++) {
            $this->RegisterAttributeInteger("StartPrio$p", 0);
        }

        $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');

        // Profile
        if (!IPS_VariableProfileExists("IPSBW.Duration")) {
            IPS_CreateVariableProfile("IPSBW.Duration", 1);
            IPS_SetVariableProfileText("IPSBW.Duration", "", " s");
            IPS_SetVariableProfileDigits("IPSBW.Duration", 0);
            IPS_SetVariableProfileValues("IPSBW.Duration", 1, 3600, 1);
        }
        if (!IPS_VariableProfileExists("IPSBW.Prioritaet")) {
            IPS_CreateVariableProfile("IPSBW.Prioritaet", 1);
            IPS_SetVariableProfileText("IPSBW.Prioritaet", "", "");
            IPS_SetVariableProfileValues("IPSBW.Prioritaet", 1, 20, 1);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        if ($zoneCount < 1) $zoneCount = 1;
        if ($zoneCount > 10) $zoneCount = 10;

        // Normale Zonen
        for ($i = 1; $i <= $zoneCount; $i++) {
            $zoneName = $this->ReadPropertyString("ZoneName$i");

            $this->RegisterVariableBoolean("Manuell$i", "Manuell $zoneName", "~Switch", 1000 + $i * 10);
            $this->EnableAction("Manuell$i");
            $this->RegisterVariableBoolean("Automatik$i", "Automatik $zoneName", "~Switch", 1001 + $i * 10);
            $this->EnableAction("Automatik$i");
            $this->RegisterVariableInteger("Dauer$i", "Dauer $zoneName", "IPSBW.Duration", 1002 + $i * 10);
            $this->EnableAction("Dauer$i");
            $this->RegisterVariableInteger("Prio$i", "Priorität $zoneName", "IPSBW.Prioritaet", 1003 + $i * 10);
            $this->EnableAction("Prio$i");
            $this->RegisterVariableBoolean("Status$i", "Status $zoneName (EIN/AUS)", "~Switch", 1004 + $i * 10);
            $this->RegisterVariableString("Info$i", "Info $zoneName", "", 1005 + $i * 10);

            IPS_SetName($this->GetIDForIdent("Manuell$i"), "Manuell $zoneName");
            IPS_SetName($this->GetIDForIdent("Automatik$i"), "Automatik $zoneName");
            IPS_SetName($this->GetIDForIdent("Dauer$i"), "Dauer $zoneName");
            IPS_SetName($this->GetIDForIdent("Prio$i"), "Priorität $zoneName");
            IPS_SetName($this->GetIDForIdent("Status$i"), "Status $zoneName (EIN/AUS)");
            IPS_SetName($this->GetIDForIdent("Info$i"), "Info $zoneName");

            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $infoID = $this->GetIDForIdent("Info$i");
            if ($aktorID == 0) {
                SetValueString($infoID, "Bitte KNX-Aktor für $zoneName auswählen!");
            }
        }

        // Nebenstelle (Zone 11, Name fest)
        $nebenName = "Nebenstelle";
        $this->RegisterVariableBoolean("Manuell11", "Manuell $nebenName", "~Switch", 1110);
        $this->EnableAction("Manuell11");
        $this->RegisterVariableBoolean("Automatik11", "Automatik $nebenName", "~Switch", 1111);
        $this->EnableAction("Automatik11");
        $this->RegisterVariableInteger("Dauer11", "Dauer $nebenName", "IPSBW.Duration", 1112);
        $this->EnableAction("Dauer11");
        $this->RegisterVariableInteger("Prio11", "Priorität $nebenName", "IPSBW.Prioritaet", 1113);
        $this->EnableAction("Prio11");
        $this->RegisterVariableBoolean("Status11", "Status $nebenName (EIN/AUS)", "~Switch", 1114);
        $this->RegisterVariableString("Info11", "Info $nebenName", "", 1115);

        IPS_SetName($this->GetIDForIdent("Manuell11"), "Manuell $nebenName");
        IPS_SetName($this->GetIDForIdent("Automatik11"), "Automatik $nebenName");
        IPS_SetName($this->GetIDForIdent("Dauer11"), "Dauer $nebenName");
        IPS_SetName($this->GetIDForIdent("Prio11"), "Priorität $nebenName");
        IPS_SetName($this->GetIDForIdent("Status11"), "Status $nebenName (EIN/AUS)");
        IPS_SetName($this->GetIDForIdent("Info11"), "Info $nebenName");

        $aktorID = $this->ReadPropertyInteger("AktorID11");
        $infoID = $this->GetIDForIdent("Info11");
        if ($aktorID == 0) {
            SetValueString($infoID, "Bitte KNX-Aktor für $nebenName auswählen!");
        }

        IPS_SetName($this->GetIDForIdent("PumpeManuell"), "Pumpe Manuell");
        IPS_SetName($this->GetIDForIdent("PumpeStatus"), "Pumpe Status");
        IPS_SetName($this->GetIDForIdent("PumpeInfo"), "Pumpe Info");

        $this->SetTimerInterval("EvaluateTimer", 1000);
        $this->ResetAllPrioStarts();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == "GesamtAutomatik") {
            if ($Value) {
                $this->ResetAllPrioStarts();
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
            // Timer zuerst komplett entfernen
            $this->UnregisterTimer("EvaluateTimer");
            // Und sofort wieder neu anlegen (frisch, mit aktuellem Intervall & Callback)
            $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');
            IPS_LogMessage("BWZ-Timer", "EvaluateTimer wurde komplett gelöscht und neu erstellt");
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

        // Pumpe immer "an", solange Automatik läuft (optional anpassbar!)
        if ($gesamtAuto) {
            SetValue($this->GetIDForIdent("PumpeStatus"), true);
            SetValue($this->GetIDForIdent("PumpeInfo"), "Automatik aktiv");
        } else {
            $manPumpe = GetValue($this->GetIDForIdent("PumpeManuell"));
            SetValue($this->GetIDForIdent("PumpeStatus"), $manPumpe);
            SetValue($this->GetIDForIdent("PumpeInfo"), $manPumpe ? "Manuell an" : "Manuell aus");
        }

        // --- Pumpe Aktor ANSTEUERN (neu) ---
        $pumpeAktorID = $this->ReadPropertyInteger("PumpeAktorID");
        $pumpeStatus = GetValue($this->GetIDForIdent("PumpeStatus"));
        if ($pumpeAktorID > 0 && @IPS_ObjectExists($pumpeAktorID)) {
            @RequestAction($pumpeAktorID, $pumpeStatus);
        }

        if ($gesamtAuto) {
            // Automatik: Nach Prio und Dauer, Zonen nacheinander
            $prioMap = [];
            // Normale Zonen + Nebenstelle einbeziehen
            for ($i = 1; $i <= $zoneCount + 1; $i++) {
                $auto = GetValue($this->GetIDForIdent("Automatik$i"));
                $prio = GetValue($this->GetIDForIdent("Prio$i"));
                $dauer = GetValue($this->GetIDForIdent("Dauer$i"));
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                $statusID = $this->GetIDForIdent("Status$i");
                $infoID = $this->GetIDForIdent("Info$i");

                if ($auto && $aktorID > 0 && @IPS_ObjectExists($aktorID)) {
                    if (!isset($prioMap[$prio])) $prioMap[$prio] = [];
                    $prioMap[$prio][] = [
                        'index' => $i,
                        'dauer' => $dauer,
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
                        SetValueString($z['infoID'], "Automatik für Prio $prio bereits erledigt");
                    }
                    continue;
                }
                if ($startPrio <= 0) {
                    $startPrio = $now + $globalOffset;
                    $this->WriteAttributeInteger($startAttr, $startPrio);
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
                        SetValueString($z['infoID'], "Automatik läuft noch $rest Sek. (Prio $prio)");
                    } else {
                        if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                            $this->SafeRequestAction($z['aktorID'], false, $z['statusID'], $z['infoID'], "");
                        }
                        $wait = $startPrio - $now;
                        SetValueString($z['infoID'], "Automatik Start in " . ($wait > 0 ? $wait : 0) . " Sek. (Prio $prio)");
                    }
                }
                $globalOffset += $maxDauer;
            }

            // Prüfen, ob noch Automatik läuft – sonst Automatik abschalten
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

            if (!$irgendetwasAktiv) {
                SetValue($this->GetIDForIdent("GesamtAutomatik"), false);
            }

            return;
        }

        // Wenn Automatik aus, dann Manuell zulassen
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
        // Nebenstelle (Zone 11)
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
    private function ManualStepAdvance()
    {
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        for ($i = 1; $i <= $zoneCount; $i++) {
            $statusID = $this->GetIDForIdent("Status$i");
            $dauerID  = $this->GetIDForIdent("Dauer$i");
            if (!@IPS_VariableExists($statusID)) {
                continue;
            }
            $status = GetValueBoolean($statusID);
    
            if ($status) {
                // Nur Restlaufzeit auf 0 setzen, keine Automatik abschalten!
                if (@IPS_VariableExists($dauerID)) {
                    SetValueInteger($dauerID, 0);
                    IPS_LogMessage("BWZ", "Restlaufzeit Zone $i auf 0 gesetzt (ID $dauerID)");
                }
                // NICHT: SetValueBoolean($this->GetIDForIdent("Automatik$i"), false);
                // NICHT: SetValueBoolean($this->GetIDForIdent("GesamtAutomatik"), false);
                break; // Nach erstem Treffer abbrechen!
            }
        }
    }
}
?>
