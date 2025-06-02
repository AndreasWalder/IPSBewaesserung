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
            $this->RegisterVariableBoolean("Status$i", "Status Zone $i (EIN/AUS)", "~Switch", 1004 + $i * 10);
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
            // Alle Startzeiten zurücksetzen, damit bei neuem Start frisch gezählt wird
            $this->ResetAllPrioStarts();
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
            $this->ResetAllPrioStarts();
            return; // Keine Zonen für Automatik aktiv
        }

        // Für jede Prio-Stufe persistenten Startzeitpunkt merken und verwenden
        ksort($prioMap, SORT_NUMERIC);
        $globalOffset = 0;
        foreach ($prioMap as $prio => $zoneArray) {
            $startAttr = "StartPrio" . $prio;
            $prioDauer = $this->getPrioDauer($zoneArray);

            // Startzeit prüfen/setzen
            $startPrio = intval($this->GetAttribute($startAttr));
            if ($startPrio <= 0 || $now > $startPrio + $prioDauer) {
                // Startzeit NICHT gesetzt oder abgelaufen -> NEU setzen
                $startPrio = $now + $globalOffset;
                $this->SetAttribute($startAttr, $startPrio);
            }

            $maxDauer = 0;
            foreach ($zoneArray as $z) {
                $ende = $startPrio + $z['dauer'];
                if ($z['dauer'] > $maxDauer) $maxDauer = $z['dauer'];

                $nowActive = ($now >= $startPrio && $now < $ende);
                if ($nowActive) {
                    if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                        RequestAction($z['aktorID'], true);
                        SetValueBoolean($z['statusID'], true);
                    }
                    $rest = $ende - $now;
                    SetValueString($z['infoID'], "Automatik läuft noch " . $rest . " Sek. (Prio $prio)");
                } else {
                    if ($z['aktorID'] > 0 && @IPS_ObjectExists($z['aktorID'])) {
                        RequestAction($z['aktorID'], false);
                        SetValueBoolean($z['statusID'], false);
                    }
                    $wait = $startPrio - $now;
                    SetValueString($z['infoID'], "Automatik Start in " . ($wait > 0 ? $wait : 0) . " Sek. (Prio $prio)");
                }
            }
            $globalOffset += $maxDauer;
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

    // Alle Startzeiten für Prio-Gruppen zurücksetzen
    private function ResetAllPrioStarts()
    {
        for ($prio = 0; $prio <= 99; $prio++) {
            $this->SetAttribute("StartPrio" . $prio, 0);
        }
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
