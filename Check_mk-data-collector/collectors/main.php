<?php
require_once(APPROOT . 'collectors/CheckmkOSFamilyCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkOSVersionCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkBrandCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkModelCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkPCCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkServerCollector.class.inc.php');
require_once(APPROOT . 'collectors/CheckmkPhysicalInterfaceCollector.class.inc.php');
$idx = 1;
Orchestrator::AddCollector($idx++, 'CheckmkOSFamilyCollector');
Orchestrator::AddCollector($idx++, 'CheckmkOSVersionCollector');
Orchestrator::AddCollector($idx++, 'CheckmkBrandCollector');
Orchestrator::AddCollector($idx++, 'CheckmkModelCollector');
Orchestrator::AddCollector($idx++, 'CheckmkPCCollector');
Orchestrator::AddCollector($idx++, 'CheckmkServerCollector');
Orchestrator::AddCollector($idx++, 'CheckmkPhysicalInterfaceCollector');
?>