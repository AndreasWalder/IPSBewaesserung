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
            $this->RegisterPropertyInteger("AktorID$i", 0);
        }

        $this->RegisterVariableBoolean("GesamtAutomatik", "Automatik Gesamtsystem", "~Switch", 900);
        $this->EnableAction("GesamtAutomatik");

        // Prio-Startzeiten (jetzt: -1 = fertig, 0 = nicht gestartet, >0 = aktiv)
        for ($p = 0; $p <= 99; $p++) {
            $this->RegisterAttributeInteger("StartPrio$p", 0);
        }

        $this->RegisterTimer("EvaluateTimer", 1000, 'IPS_RequestAction($_IPS["TARGET"], "Evaluate", 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $zoneCount = $this->ReadPropertyInteger("ZoneCount");
        if ($zoneCount < 1) $zoneCount = 1;
        if ($zoneCount > 10) $zoneCount = 10;

        for ($i = 1; $i <= $zoneCount; $i++) {
            $this->RegisterVariableBoolean("Manuell$i", "Manuell Zone $i", "~Switch", 1000 + $i * 10);
            $this->EnableAction("Manuell$i");

            $this->RegisterVariableBoolean("Automatik$i", "Automatik Zone $i", "~Switch", 1001 + $i * 10);
            $this->EnableAction("Automatik$i");

            $this->RegisterVariableInteger("Dauer$i", "Dauer Zone $i", "IPSBW.Duration", 1002 + $i * 10);
            $this->EnableAction("Dauer$i");

            $this->RegisterVariableInteger("Prio$i", "Priorität Zone $i", "IPSBW.Prioritaet", 1003 + $i * 10);
            $this->EnableAction("Prio$i");

            $this->RegisterVariableBoolean("Status$i", "Status Zone $i (EIN/AUS)", "~Switch", 1004 + $i * 10);
            $this->RegisterVariableString("Info$i", "Info Zone $i", "", 1005 + $i * 10);

            // Warnung bei fehlender AktorID
            $aktorID = $this->ReadPropertyInteger("AktorID$i");
            $infoID = $this->GetIDForIdent("Info$i");
            if ($aktorID == 0) {
                SetValueString($infoID, "Bitte KNX-Aktor für Zone $i auswählen!");
            }
        }
        $this->SetTimerInterval("EvaluateTimer", 1000);

        // Nach Änderung: alle Prio wieder „offen“
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
            // JEDES MAL bei Einschalten: alle Prio auf Anfang!
            if ($Value) {
                $this->ResetAllPrioStarts();
            }
            SetValue($this->GetIDForIdent($Ident), $Value);
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

        if ($gesamtAuto) {
            // Automatik hat Vorrang, Manuell ist gesperrt
            for ($i = 1; $i <= $zoneCount; $i++) {
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

            // Automatik-Logik mit Prio-Status „fertig“-Prüfung
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

                // Bereits erledigte Prio (=-1) überspringen
                if ($startPrio === -1) {
                    foreach ($zoneArray as $z) {
                        SetValueBoolean($z['statusID'], false);
                        SetValueString($z['infoID'], "Automatik für Prio $prio bereits erledigt");
                    }
                    continue;
                }

                // Starten, wenn noch nie gelaufen oder aus vorherigem Reset
                if ($startPrio <= 0) {
                    $startPrio = $now + $globalOffset;
                    $this->WriteAttributeInteger($startAttr, $startPrio);
                }

                // Prio fertig? Dann als erledigt markieren und nie wieder starten!
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

            // Prüfen, ob noch irgendwo Automatik läuft – sonst Automatik abschalten!
            $irgendetwasAktiv = false;
            foreach ($prioMap as $prio => $zoneArray) {
                $startAttr = "StartPrio" . $prio;
                $startPrio = $this->ReadAttributeInteger($startAttr);
                if ($startPrio === -1) continue; // fertig
                foreach ($zoneArray as $z) {
                    $ende = $startPrio + $z['dauer'];
                    if ($now >= $startPrio && $now < $ende) {
                        $irgendetwasAktiv = true;
                        break 2; // Sofort abbrechen
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
        // KEINE Automatik-Logik mehr, wenn Automatik aus ist!
    }

    private function getPrioDauer($zoneArray)
    {
        $max = 0;
        foreach ($zoneArray as $z) {
            if ($z['dauer'] > $max) $max = $z['dauer'];
        }
        return $max;
    }

    // Setzt ALLE Prio-Startzeiten auf "offen" (beim Übernehmen, Button oder Automatik-Start)
    private function ResetAllPrioStarts()
    {
        for ($prio = 0; $prio <= 99; $prio++) {
            $this->WriteAttributeInteger("StartPrio" . $prio, 0);
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

        // Zusatz-Button: Alle Prio zurücksetzen (optional)
        $actions = [
            [
                "type"    => "Button",
                "caption" => "Alle Automatik-Prio wieder freigeben",
                "onClick" => "IPS_RequestAction($id, 'ResetAll', 0);"
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
