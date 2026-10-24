<?php
$planpointTransportClass=class_exists('xPDO\\Transport\\xPDOTransport')?'xPDO\\Transport\\xPDOTransport':'xPDOTransport';
if ($options[$planpointTransportClass::PACKAGE_ACTION]===$planpointTransportClass::ACTION_UNINSTALL) return true;
$modx=$object->xpdo;
$version=$modx->getVersionData();$full=$version['full_version'];
$isV3=strpos($full,'3.2.')===0;
if(strpos($full,'2.8.')!==0&&!$isV3){$modx->log(1,'PlanPoint: поддерживаются MODX 2.8.x и экспериментально 3.2.x.');return false;}
$php=$isV3?'8.1.0':'7.4.0';
if(version_compare(PHP_VERSION,$php,'<')){$modx->log(1,'PlanPoint: требуется PHP '.$php.'+.');return false;}
foreach(array('dom','mbstring','ctype','pdo','pdo_mysql')as$extension)if(!extension_loaded($extension)){$modx->log(1,'PlanPoint: отсутствует расширение PHP '.$extension);return false;}
return true;
