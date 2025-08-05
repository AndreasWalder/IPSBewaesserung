<?php
trait BewaesserungHelper
{
    // Sicheres Schalten eines Aktors inkl. Status und Info-Update
    protected function SafeRequestAction($aktorID, $value, $statusID, $infoID, $okText = "")
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

    // Sicheres Setzen eines Bool-Werts + Info (wird aktuell nicht genutzt, aber für künftige Erweiterungen praktisch)
    protected function SafeSetValueBoolean($statusID, $value, $infoID, $text = "")
    {
        SetValueBoolean($statusID, $value);
        if ($text != "") {
            SetValueString($infoID, $text);
        }
    }

    // Setzt ALLE Prio-Startzeiten auf "offen" (0)
    protected function ResetAllPrioStarts()
    {
        for ($prio = 0; $prio <= 99; $prio++) {
            $this->WriteAttributeInteger("StartPrio" . $prio, 0);
        }
    }

    // Liefert die längste Laufzeit einer Prio-Gruppe (für Automatik-Sequenz)
    protected function getPrioDauer($zoneArray)
    {
        $max = 0;
        foreach ($zoneArray as $z) {
            if ($z['dauer'] > $max) $max = $z['dauer'];
        }
        return $max;
    }
}
?>
