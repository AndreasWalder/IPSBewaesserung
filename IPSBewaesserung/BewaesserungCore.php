<?php
class BewaesserungCore extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Neue Variable für manuellen Schrittwechsel
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
    
        // >>> Manueller Schrittwechsel <<<
        if ($Ident == "ManualNextStep") {
            if ($Value) {
                $this->ManualStepAdvance();
                $this->SetValue("ManualNextStep", false);
            }
            return;
        }
        // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
    
        if ($Ident == "ResetAll") {
            $this->ResetAllPrioStarts();
            $this->Evaluate();
            return;
        }
    
        if ($Ident == "RestartTimer") {
            $this->SetTimerInterval("EvaluateTimer", 1000);
            IPS_LogMessage("BWZ-Timer", "Timer wurde manuell neu gestartet");
            return;
        }
    
        if ($Ident == "Evaluate") {
            $this->Evaluate();
            return;
        }
    
        SetValue($this->GetIDForIdent($Ident), $Value);
    }

    // HIER: Die Methode für den manuellen Schrittwechsel!
    private function ManualStepAdvance()
    {
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        $found = false;
        for ($i = 1; $i <= $zoneCount; $i++) {
            $statusID = $this->GetIDForIdent("Status$i");
            if (!@IPS_VariableExists($statusID)) {
                continue;
            }
            $status = GetValueBoolean($statusID);

            if ($status && !$found) {
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                if ($aktorID > 0) {
                    RequestAction($aktorID, false);
                }
                SetValueBoolean($statusID, false);
                $found = true;
            } elseif ($found && !$status) {
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                if ($aktorID > 0) {
                    RequestAction($aktorID, true);
                }
                SetValueBoolean($statusID, true);
                break;
            }
        }
    }

    public function Evaluate()
    {
        //IPS_LogMessage("IPSBewaesserung", "Evaluate() wurde aufgerufen.");
        // Hier kann später deine Ablaufsteuerung ergänzt werden
    }
}
?>
