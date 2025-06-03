public function GetConfigurationForm()
{
    $zoneCount = $this->ReadPropertyInteger("ZoneCount");
    if ($zoneCount < 1) $zoneCount = 1;
    if ($zoneCount > 10) $zoneCount = 10;

    $elements = [];

    // Zonenanzahl
    $elements[] = [
        "type" => "NumberSpinner",
        "name" => "ZoneCount",
        "caption" => "Anzahl der Zonen",
        "minimum" => 1,
        "maximum" => 10
    ];

    // Für jede Zone: Name und Aktor-Auswahl
    for ($i = 1; $i <= $zoneCount; $i++) {
        $elements[] = [
            "type"    => "ValidationTextBox",
            "name"    => "ZoneName$i",
            "caption" => "Name der Zone $i"
        ];
        $elements[] = [
            "type"    => "SelectObject",
            "name"    => "AktorID$i",
            "caption" => "KNX Aktor-Variable Zone $i"
        ];
    }

    // Nebenstelle (Zone 11)
    $elements[] = [
        "type"    => "Label",
        "label"   => "Nebenstelle (Zone 11, optional)"
    ];
    $elements[] = [
        "type"    => "SelectObject",
        "name"    => "AktorID11",
        "caption" => "KNX Aktor-Variable Nebenstelle"
    ];

    // Pumpe
    $elements[] = [
        "type"    => "Label",
        "label"   => "Pumpe (optional)"
    ];
    $elements[] = [
        "type"    => "SelectObject",
        "name"    => "PumpeAktorID",
        "caption" => "KNX Aktor-Variable Pumpe"
    ];

    // Aktionen/Buttons
    $actions = [
        [
            "type"    => "Button",
            "caption" => "Alle Automatik-Prio wieder freigeben",
            "onClick" => "IPS_RequestAction($id, 'ResetAll', 0);"
        ],
        [
            "type"    => "Button",
            "caption" => "Timer neu starten",
            "onClick" => "IPS_RequestAction($id, 'RestartTimer', 0);"
        ]
    ];

    $form = [
        "elements" => $elements,
        "actions"  => $actions
    ];

    return json_encode($form);
}
