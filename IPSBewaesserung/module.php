<?php
require_once __DIR__ . '/BewaesserungCore.php';
require_once __DIR__ . '/BewaesserungConfig.php';
require_once __DIR__ . '/BewaesserungHelper.php';

class IPSBewaesserung extends BewaesserungCore
{
    use BewaesserungConfig, BewaesserungHelper;
}
?>