<?php
require_once MODX_CORE_PATH.'components/planpoint/model/planpoint.php';
$pe=new PlanPoint($modx);
$id=(int)$modx->getOption('floorId',$scriptProperties,$modx->resource?$modx->resource->get('id'):0);
return $pe->render($id,$modx->getOption('tpl',$scriptProperties,'PlanPointScheme'),$modx->getOption('tplEmpty',$scriptProperties,'PlanPointEmpty'),!empty($scriptProperties['inner']));
