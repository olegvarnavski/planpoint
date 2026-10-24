<?php
// Do not let a disabled/unconfigured component break unrelated resource editors.
if ($modx->event->name!=='OnDocFormPrerender' || empty($resource) || !$resource->get('id') || !$resource->checkPolicy('view')) return;
$template=(int)$resource->get('template');
$houses=array_map('intval',explode(',',$modx->getOption('planpoint.house_templates',null,'')));
$floors=array_map('intval',explode(',',$modx->getOption('planpoint.floor_templates',null,'8')));
if (!in_array($template,$houses,true) && !in_array($template,$floors,true)) return;
try {
    require_once MODX_CORE_PATH.'components/planpoint/model/planpoint.php';
    $pe=new PlanPoint($modx);
    $isHouse=in_array($template,$houses,true);$isFloor=in_array($template,$floors,true);
    $houseId=$isHouse?(int)$resource->get('id'):(int)$pe->houseForFloor($resource->get('id'))->get('id');
    $pe->house($houseId);
    $url=$modx->getOption('assets_url').'components/planpoint/';
    $config=array('houseId'=>$houseId,'floorId'=>$isFloor?(int)$resource->get('id'):0,'sourceId'=>$pe->sourceId(),'connector'=>$url.'connector.php','version'=>'0.3.0-alpha','mixedImage'=>null);
    // The installed mixedImage must explicitly support MODX 3 before using its TV adapter.
    if (!PlanPointPlatform::isV3() || $modx->getOption('planpoint.modx3_mixedimage',null,false)) {
        try {$config['mixedImage']=$pe->pickerConfig($houseId);}catch(Throwable $e){$modx->log(1,'[PlanPoint image adapter] '.$e->getMessage());}
    }
    if ($config['mixedImage']) {
        $modx->lexicon->load('mixedimage:default');
        $modx->regClientCSS($modx->getOption('assets_url').'components/mixedimage/css/mgr/mixedimage.css');
        $modx->regClientStartupScript($modx->getOption('assets_url').'components/mixedimage/js/mgr/mixedimage.js');
    }
    foreach(array('ace','mode-html','theme-textmate') as $file)$modx->regClientStartupScript($url.'vendor/ace/'.$file.'.js');
    $modx->regClientCSS($url.'css/manager.css?v=0.3.0');
    $modx->regClientStartupHTMLBlock('<script>window.PlanPointConfig='.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>');
    foreach(array('geometry','editor','manager') as $file)$modx->regClientStartupScript($url.'js/'.$file.'.js?v=0.3.0');
} catch(Throwable $e) {
    $modx->log(1,'[PlanPoint manager] resource='.(int)$resource->get('id').' '.$e->getMessage());
}
