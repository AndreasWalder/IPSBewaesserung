<?php
class BewaesserungCore extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger("ZoneCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyString("ZoneName$i", "Zone $i");
            $this->RegisterPropertyInteger("AktorID$i", 0);
        }
        $this->RegisterPropertyInteger("PumpeAktorID", 0);

        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        // Pumpe
        $this->RegisterVariableBoolean("PumpeManuell", "Pumpe Manuell", "~Switch", 950);
        $this->EnableAction("PumpeManuell");
        $this->RegisterVariableBoolean("PumpeStatus", "Pumpe Status", "~Switch", 951);
        $this->RegisterVariableString("PumpeInfo", "Pumpe Info", "", 952);

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

            // Warnung bei fehlender AktorID
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $infoID = $this->GetIDForIdent("Info$i");
            if ($aktorID == 0) {
                SetValueString($infoID, "Bitte KNX-Aktor für $zoneName auswählen!");
            }
        }
        $this->SetTimerInterval("EvaluateTimer", 1000);
        $this->ResetAllPrioStarts();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == "Evaluate") {
            $this->Evaluate();
            return;
        }
        if ($Ident == "ResetAll") {
            $this->ResetAllPrioStarts();
            return;
        }
        if ($Ident == "GesamtAutomatik") {
            if ($Value) {
                $this->ResetAllPrioStarts();
            }
            SetValue($this->GetIDForIdent($Ident), $Value);
            return;
        }
        if ($Ident == "PumpeManuell") {
            SetValue($this->GetIDForIdent("PumpeManuell"), $Value);
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
        $pumpeAktorID = $this->ReadPropertyInteger("PumpeAktorID");
        $pumpeManuell = GetValue($this->GetIDForIdent("PumpeManuell"));
        $pumpeStatusID = $this->GetIDForIdent("PumpeStatus");
        $pumpeInfoID = $this->GetIDForIdent("PumpeInfo");

        $pumpeSollAn = $gesamtAuto || $pumpeManuell;

        if ($pumpeAktorID > 0 && @IPS_ObjectExists($pumpeAktorID)) {
            $this->SafeRequestAction($pumpeAktorID, $pumpeSollAn, $pumpeStatusID, $pumpeInfoID, $pumpeSollAn ? "Pumpe EIN" : "Pumpe AUS");
        } else {
            SetValueBoolean($pumpeStatusID, false);
            SetValueString($pumpeInfoID, "Keine Pumpe-AktorID");
        }

        // (Zonen-Logik wäre hier, für Kürze ausgelassen.)
    }

    // ... Restliche Methoden wie getPrioDauer(), ResetAllPrioStarts(), SafeRequestAction(), SafeSetValueBoolean() ...
}
?>
