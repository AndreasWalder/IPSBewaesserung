<?php
class IPSBewaesserung extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Profile für Dauer und Prio
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

        $this->RegisterPropertyInteger("ZoneCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyString("ZoneName$i", "Zone $i");
            $this->RegisterPropertyInteger("AktorID$i", 0);
        }
        $this->RegisterPropertyInteger("PumpeAktorID", 0);

        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        // Prio-Startzeiten
        for ($p = 0; $p <= 99; $p++) {
            $this->RegisterAttributeInteger("StartPrio$p", 0);
        }

        $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');

        // Pumpe: Variablen
        $this->RegisterVariableBoolean("PumpeManuell", "Pumpe Manuell", "~Switch", 950);
        $this->EnableAction("PumpeManuell");
        $this->RegisterVariableBoolean("PumpeStatus", "Pumpe Status", "~Switch", 951);
        $this->RegisterVariableString("PumpeInfo", "Pumpe Info", "", 952);
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

        $pumpeSollAn = false;

        if ($gesamtAuto) {
            $pumpeSollAn = true;
        } else {
            if ($pumpeManuell) {
                $pumpeSollAn = true;
            }
        }

        if ($pumpeAktorID > 0 && @IPS_ObjectExists($pumpeAktorID)) {
            $this->SafeRequestAction($pumpeAktorID, $pumpeSollAn, $pumpeStatusID, $pumpeInfoID, $pumpeSollAn ? "Pumpe EIN" : "Pumpe AUS");
        } else {
            SetValueBoolean($pumpeStatusID, false);
            SetValueString($pumpeInfoID, "Keine Pumpe-AktorID");
        }

        // ----------- Zonen-Logik -----------
        if ($gesamtAuto) {
            for ($i = 1; $i <= $zoneCount; $i++) {
                $zoneName = $this->ReadPropertyString("ZoneName$i");
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                $statusID = $this->GetIDForIdent("Status$i");
                $infoID = $this->GetIDForIdent("Info$i");
                if ($aktorID > 0 && @IPS_ObjectExists($aktorID)) {
                    SetValueString($infoID, "Automatik aktiv, Manuell gesperrt");
                } else {
                    SetValueBoolean($statusID, false);
                    SetValueString($infoID, "Keine AktorID");
                }
            }

            $prioMap = [];
            for ($i = 1; $i <= $zoneCount; $i++) {
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
                        $this->SafeSetValueBoolean($z['statusID'], false, $z['infoID'], "Automatik für Prio $prio bereits erledigt");
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
                        $this->SafeSetValueBoolean($z['statusID'], false, $z['infoID'], "Automatik für Prio $prio erledigt");
                    }
                    continue;
                }

                $maxDauer = 0;
                foreach ($zoneArray as $z) {
                    $ende = $startPrio + $z['dauer'];
                    if ($z['dauer'] > $maxDauer) $maxDauer = $z['dauer'];
                    $nowActive = ($now >= $startPrio && $now < $ende);
                    if ($nowActive) {
                        $this->SafeRequestAction($z['aktorID'], true, $z['statusID'], $z['infoID']);
                        $rest = $ende - $now;
                        SetValueString($z['infoID'], "Automatik läuft noch " . $rest . " Sek. (Prio $prio)");
                    } else {
                        $this->SafeRequestAction($z['aktorID'], false, $z['statusID'], $z['infoID']);
                        $wait = $startPrio - $now;
                        SetValueString($z['infoID'], "Automatik Start in " . ($wait > 0 ? $wait : 0) . " Sek. (Prio $prio)");
                    }
                }
                $globalOffset += $maxDauer;
            }

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

        // --------- Nur wenn Automatik AUS: Manuell möglich! ----------
        for ($i = 1; $i <= $zoneCount; $i++) {
            $zoneName = $this->ReadPropertyString("ZoneName$i");
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
        // Pumpe manuell nachziehen
        if (!$gesamtAuto) {
            if ($pumpeAktorID > 0 && @IPS_ObjectExists($pumpeAktorID)) {
                $this->SafeRequestAction($pumpeAktorID, $pumpeManuell, $pumpeStatusID, $pumpeInfoID, $pumpeManuell ? "Pumpe EIN" : "Pumpe AUS");
            } else {
                SetValueBoolean($pumpeStatusID, false);
                SetValueString($pumpeInfoID, "Keine Pumpe-AktorID");
            }
        }
    }

    private function getPrioDauer($zoneArray)
    {
        $max = 0;
        foreach ($zoneArray as $z) {
            if ($z['dauer'] > $max) $max = $z['dauer'];
        }
        return $max;
    }

    private function ResetAllPrioStarts()
    {
        for ($prio = 0; $prio <= 99; $prio++) {
            $this->WriteAttributeInteger("StartPrio" . $prio, 0);
        }
    }

    private function SafeRequestAction($aktorID, $value, $statusID, $infoID, $okText = "")
    {
        if ($aktorID > 0 && @IPS_ObjectExists($aktorID)) {
            try {
                if (@RequestAction($aktorID, $value) !== false) {
                    SetValueBoolean($statusID, $value);
                    if ($okText != "") {
                        SetValueString($infoID, $okText);
                    }
                } else {
                    SetValueBoolean($statusID, false);
                    SetValueString($infoID, "Fehler beim Schalten (RequestAction)!");
                }
            } catch (Exception $e) {
                SetValueBoolean($statusID, false);
                SetValueString($infoID, "Fehler beim Schalten: " . $e->getMessage());
            }
        } else {
            SetValueBoolean($statusID, false);
            SetValueString($infoID, "Aktor existiert nicht!");
        }
    }

    private function SafeSetValueBoolean($statusID, $value, $infoID, $text = "")
    {
        SetValueBoolean($statusID, $value);
        if ($text != "") {
            SetValueString($infoID, $text);
        }
    }

    public function GetConfigurationForm()
    {
        // --- OPTIONAL: Einlesen aus externer Datei ---
        // return file_get_contents(__DIR__ . '/configform.json');

        // --- Dynamische Generierung wie bisher ---
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        if ($zoneCount < 1) $zoneCount = 1;
        if ($zoneCount > 10) $zoneCount = 10;

        $elements = [];
        $elements[] = [
            "type" => "NumberSpinner",
            "name" => "ZoneCount",
            "caption" => "Anzahl der Zonen",
            "minimum" => 1,
            "maximum" => 10
        ];

        for ($i = 1; $i <= $zoneCount; $i++) {
            $elements[] = [
                "type" => "ValidationTextBox",
                "name" => "ZoneName$i",
                "caption" => "Name der Zone $i"
            ];
            $elements[] = [
                "type" => "SelectObject",
                "name" => "AktorID$i",
                "caption" => "KNX Aktor-Variable Zone $i"
            ];
        }

        $elements[] = [
            "type" => "SelectObject",
            "name" => "PumpeAktorID",
            "caption" => "Pumpen-Aktor-Variable"
        ];

        $actions = [
            [
                "type"    => "Button",
                "caption" => "Alle Automatik-Prio wieder freigeben",
                "onClick" => "IPS_RequestAction(" . $this->InstanceID . ", 'ResetAll', 0);"
            ]
        ];

        $form = [
            "elements" => $elements,
            "actions"  => $actions
        ];

        return json_encode($form);
    }
}
?>
