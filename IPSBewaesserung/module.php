<?php
class IPSBewaesserung extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        $this->RegisterPropertyInteger("ZoneCount", 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("AktorID$i", 0);
            $this->RegisterVariableBoolean("Manuell$i", "Manuell Zone $i", "~Switch", 1000 + $i * 10);
            $this->EnableAction("Manuell$i");
            $this->RegisterVariableBoolean("Automatik$i", "Automatik Zone $i", "~Switch", 1001 + $i * 10);
            $this->EnableAction("Automatik$i");
            $this->RegisterVariableInteger("Dauer$i", "Dauer Zone $i (Sekunden)", "", 1002 + $i * 10);
            $this->RegisterVariableInteger("Prio$i", "Priorität Zone $i", "", 1003 + $i * 10);

            // Nur anzeigende Statusvariable (nicht schaltbar! kein EnableAction, kein Profil-Setzen!)
            $this->RegisterVariableBoolean("Status$i", "Status Zone $i (EIN/AUS)", "~Switch", 1004 + $i * 10);

            // Info als String (ehemals Status)
            $this->RegisterVariableString("Info$i", "Info Zone $i", "", 1005 + $i * 10);
        }
        $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value)
    {
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

        // 1. Manuell pro Zone immer Vorrang und immer direkt Status setzen!
        for ($i = 1; $i <= $zoneCount; $i++) {
            $manuell = GetValue($this->GetIDForIdent("Manuell$i"));
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $statusID = $this->GetIDForIdent("Status$i");
            $infoID = $this->GetIDForIdent("Info$i");

            if ($aktorID > 0 && @IPS_ObjectExists($aktorID)) {
                if ($manuell) {
                    RequestAction($aktorID, true);
                    SetValueBoolean($statusID, true);
                    SetValueString($infoID, "Manuell eingeschaltet");
                } else {
                    RequestAction($aktorID, false);
                    SetValueBoolean($statusID, false);
                    SetValueString($infoID, "Manuell ausgeschaltet");
                }
            } else {
                SetValueBoolean($statusID, false);
                SetValueString($infoID, "Keine AktorID");
            }
        }

        // 2. Automatik (nur wenn GesamtAutomatik EIN und Manuell AUS pro Zone)
        $gesamtAuto = GetValue($this->GetIDForIdent("GesamtAutomatik"));
        if (!$gesamtAuto) {
            // Automatik global aus, Rest ist schon gestoppt
            return;
        }

        // PrioMap: Alle Zonen, die auf Automatik UND Manuell AUS stehen
        $prioMap = [];
        for ($i = 1; $i <= $zoneCount; $i++) {
            $manuell = GetValue($this->GetIDForIdent("Manuell$i"));
            $auto = GetValue($this->GetIDForIdent("Automatik$i"));
            $prio = GetValue($this->GetIDForIdent("Prio$i"));
            $dauer = GetValue($this->GetIDForIdent("Dauer$i"));
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $statusID = $this->GetIDForIdent("Status$i");
            $infoID = $this->GetIDForIdent("Info$i");

            if (!$manuell && $auto) {
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
            return; // Keine Zonen für Automatik aktiv
        }

        ksort($prioMap, SORT_NUMERIC);
        $offset = 0;
        foreach ($prioMap as $prio => $zoneArray) {
            $start = $now + $offset;
            $maxDauer = 0;
            foreach ($zoneArray as $z) {
                $ende = $start + $z['dauer'];
                if ($z['dauer'] > $maxDauer) $maxDauer = $z['dauer'];

                $nowActive = ($now >= $start && $now < $ende);
                if ($nowActive) {
                    if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                        RequestAction($z['aktorID'], true);
                        SetValueBoolean($z['statusID'], true);
                    }
                    $rest = $ende - $now;
                    SetValueString($z['infoID'], "Automatik läuft noch " . $rest . " Sek. (Prio $prio)");
                    $this->LogZoneStatus($z['index'], "Automatik gestartet (Prio $prio)", $start);
                } else {
                    if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                        RequestAction($z['aktorID'], false);
                        SetValueBoolean($z['statusID'], false);
                    }
                    $wait = $start - $now;
                    SetValueString($z['infoID'], "Automatik Start in " . ($wait > 0 ? $wait : 0) . " Sek. (Prio $prio)");
                    if ($now == $ende) {
                        $this->LogZoneStatus($z['index'], "Automatik beendet (Prio $prio)", $ende);
                    }
                }
            }
            $offset += $maxDauer;
        }
    }

    private function LogZoneStatus($zone, $status, $zeit)
    {
        $logMsg = "Zone $zone $status um " . date("d.m.Y H:i:s", $zeit);
        IPS_LogMessage("IPSBewaesserung", $logMsg);
        // Optional: Log in String-Variable
    }

    public function GetConfigurationForm()
    {
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
                "type" => "SelectObject",
                "name" => "AktorID$i",
                "caption" => "KNX Aktor-Variable Zone $i"
            ];
        }

        $form = [
            "elements" => $elements,
            "actions"  => []
        ];

        return json_encode($form);
    }
}
?>
