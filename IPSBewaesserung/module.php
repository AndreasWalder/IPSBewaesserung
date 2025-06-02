<?php
class IPSBewaesserung extends IPSModule {

    public function Create() {
        parent::Create();
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
        $this->RegisterTimer("EvaluateTimer", 60000, 'IPSBewaesserung_Evaluate($_IPS["TARGET"]);');
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value) {
        SetValue($this->GetIDForIdent($Ident), $Value);
    }

    public function Evaluate() {
        $now = time();
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        $zones = [];
        for ($i = 1; $i <= $zoneCount; $i++) {
            $manuell = GetValue($this->GetIDForIdent("Manuell$i"));
            $auto = GetValue($this->GetIDForIdent("Automatik$i"));
            $prio = GetValue($this->GetIDForIdent("Prio$i"));
            $dauer = GetValue($this->GetIDForIdent("Dauer$i"));
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $statusID = $this->GetIDForIdent("Status$i");

            if ($manuell) {
                RequestAction($aktorID, true);
                SetValueString($statusID, "Manuell aktiv");
                continue;
            }

            if ($auto) {
                $zones[] = [
                    'index' => $i,
                    'prio' => $prio,
                    'dauer' => $dauer,
                    'aktorID' => $aktorID,
                    'statusID' => $statusID
                ];
            } else {
                RequestAction($aktorID, false);
                SetValueString($statusID, "Automatik aus");
            }
        }

        usort($zones, fn($a, $b) => $a['prio'] <=> $b['prio']);
        $offset = 0;
        foreach ($zones as $z) {
            $start = $now + $offset;
            $ende = $start + $z['dauer'];
            $nowActive = $now >= $start && $now < $ende;
            if ($nowActive) {
                RequestAction($z['aktorID'], true);
                $rest = $ende - $now;
                SetValueString($z['statusID'], "Läuft noch " . round($rest / 60) . " Min.");
            } else {
                RequestAction($z['aktorID'], false);
                SetValueString($z['statusID'], "Start in " . round(($start - $now) / 60) . " Min.");
            }
            $offset += $z['dauer'];
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
