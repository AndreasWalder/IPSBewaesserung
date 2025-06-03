<?php
trait BewaesserungConfig
{
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

        // Nebenstelle (Zone 11)
        $elements[] = [
            "type" => "Label",
            "caption" => "Nebenstelle (immer verfügbar):"
        ];
        $elements[] = [
            "type" => "SelectObject",
            "name" => "AktorID11",
            "caption" => "KNX Aktor-Variable Nebenstelle"
        ];

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
