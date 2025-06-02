<?php
class IPSBewaesserung extends IPSModule {

    public function Create() {
        parent::Create();
        // Übergeordnetes Automatik-Flag
        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        $this->RegisterPropertyInteger("ZoneCount", 10);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("AktorID$i", 0);
            $this->RegisterVariableBoolean("Manuell$i", "Manuell Zone $i", "~Switch", 1000 + $i * 10);
            $this->EnableAction("Manuell$i");
            $this->RegisterVariableBoolean("Automatik$i", "Automatik Zone $i", "~Switch", 1001 + $i * 10);
            $this->EnableAction("Automatik$i");
            $this->RegisterVariableInteger("Dauer$i", "Dauer Zone $i (s)", "", 1002 + $i * 10);
            $this->RegisterVariableInteger("Prio$i", "Priorität Zone $i", "", 1003 + $i * 10);
            $this->RegisterVariableString("Status$i", "Status Zone $i", "", 1004 + $i * 10);
        }
        $this->RegisterTimer("EvaluateTimer", 60000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value) {
        if ($Ident == "Evaluate") {
            $this->Evaluate();
            return;
        }
        SetValue($this->GetIDForIdent($Ident), $Value);
    }

    public function Evaluate() {
        $now = time();
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        $gesamtAuto = GetValue($this->GetIDForIdent("GesamtAutomatik"));

        if (!$gesamtAuto) {
            // Automatik global AUS: Alles stoppen
            for ($i = 1; $i <= $zoneCount; $i++) {
                $aktorID = $this->ReadPropertyInteger("AktorID$i");
                $statusID = $this->GetIDForIdent("Status$i");
                RequestAction($aktorID, false);
                SetValueString($statusID, "Automatik global aus");
            }
            return;
        }

        // Zonen nach Prio einsammeln
        $prioMap = []; // prio => array of zone-arrays
        for ($i = 1; $i <= $zoneCount; $i++) {
            $manuell = GetValue($this->GetIDForIdent("Manuell$i"));
            $auto = GetValue($this->GetIDForIdent("Automatik$i"));
            $prio = GetValue($this->GetIDForIdent("Prio$i"));
            $dauer = GetValue($this->GetIDForIdent("Dauer$i"));
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $statusID = $this->GetIDForIdent("Status$i");

            // 1. Manuell-Modus geht immer vor, Status sauber mitführen!
            if ($manuell) {
                $currentState = GetValue($aktorID);
                if ($currentState) {
                    SetValueString($statusID, "Manuell eingeschaltet");
                } else {
                    SetValueString($statusID, "Manuell ausgeschaltet");
                }
                // Im Manuell-Modus darf Automatik nichts schalten
                RequestAction($aktorID, $currentState); // optional redundant
                continue;
            }

            // 2. Automatik-Liste nur befüllen, wenn manuell NICHT aktiv ist!
            if ($auto) {
                if (!isset($prioMap[$prio])) $prioMap[$prio] = [];
                $prioMap[$prio][] = [
                    'index' => $i,
                    'dauer' => $dauer,
                    'aktorID' => $aktorID,
                    'statusID' => $statusID
                ];
            } else {
                RequestAction($aktorID, false);
                SetValueString($statusID, "Automatik aus");
            }
        }

        if (empty($prioMap)) {
            return; // Keine Zonen für Automatik aktiv
        }

        // Prio-Liste sortieren (aufsteigend, niedrigste Prio zuerst)
        ksort($prioMap, SORT_NUMERIC);
        $offset = 0;
        foreach ($prioMap as $prio => $zoneArray) {
            // Jede Prio-Stufe gemeinsam schalten!
            $start = $now + $offset;
            $maxDauer = 0;
            foreach ($zoneArray as $z) {
                $ende = $start + $z['dauer'];
                if ($z['dauer'] > $maxDauer) $maxDauer = $z['dauer'];

                $nowActive = ($now >= $start && $now < $ende);
                if ($nowActive) {
                    RequestAction($z['aktorID'], true);
                    $rest = $ende - $now;
                    SetValueString($z['statusID'], "Automatik läuft noch " . round($rest / 60) . " Min. (Prio $prio)");
                    $this->LogZoneStatus($z['index'], "Automatik gestartet (Prio $prio)", $start);
                } else {
                    RequestAction($z['aktorID'], false);
                    SetValueString($z['statusID'], "Automatik Start in " . round(($start - $now) / 60) . " Min. (Prio $prio)");
                    if ($now == $ende) {
                        $this->LogZoneStatus($z['index'], "Automatik beendet (Prio $prio)", $ende);
                    }
                }
            }
            // Für die nächste Prio-Ebene: max. Dauer dieser Prio addieren!
            $offset += $maxDauer;
        }
    }

    private function LogZoneStatus($zone, $status, $zeit) {
        $logMsg = "Zone $zone $status um " . date("d.m.Y H:i:s", $zeit);
        IPS_LogMessage("IPSBewaesserung", $logMsg);
        // Alternativ: Log in eine String-Variable oder in eine Datei schreiben
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
