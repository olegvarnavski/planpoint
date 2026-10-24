<?php
/** Retain data on uninstall. No destructive downgrade migrations. */
if (!isset($object) || !isset($object->xpdo)) return false;
$modx=$object->xpdo;
$planpointTransportClass=class_exists('xPDO\\Transport\\xPDOTransport')?'xPDO\\Transport\\xPDOTransport':'xPDOTransport';
if ($options[$planpointTransportClass::PACKAGE_ACTION]===$planpointTransportClass::ACTION_UNINSTALL) return true;
if (!class_exists('DOMDocument') || !extension_loaded('mbstring')) { $modx->log(1,'PlanPoint требует PHP DOM и mbstring.'); return false; }
require_once MODX_CORE_PATH.'components/planpoint/model/schema.php';
try {
    if (!$modx->connect()) throw new RuntimeException('Database unavailable');
    planpointInstallSchema($modx->pdo,$modx->getOption('table_prefix',null,'modx_'));
    return true;
} catch(Throwable $e) { $modx->log(1,'[PlanPoint install] '.$e->getMessage()); return false; }
