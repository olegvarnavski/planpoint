<?php
if ($options[xPDOTransport::PACKAGE_ACTION]===xPDOTransport::ACTION_UNINSTALL) return true;
$modx=$object->xpdo;
if (version_compare(PHP_VERSION,'7.4.0','<') || !class_exists('DOMDocument') || !extension_loaded('mbstring')) {
    $modx->log(modX::LOG_LEVEL_ERROR,'PlanPoint: PHP 7.4+, DOM, mbstring обязательны.'); return false;
}
$v=$modx->getVersionData();
if (strpos($v['full_version'],'2.8.')!==0) { $modx->log(modX::LOG_LEVEL_ERROR,'PlanPoint alpha предназначен для MODX 2.8.x.'); return false; }
return true;
