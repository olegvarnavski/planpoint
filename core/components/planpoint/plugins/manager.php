<?php
require_once MODX_CORE_PATH.'components/planpoint/model/planpoint.php';
$pe=new PlanPoint($modx);
if ($modx->event->name==='OnDocFormPrerender') {
    if (!isset($resource) || !$resource || !$resource->get('id') || !$resource->checkPolicy('view')) return;
    $template=(int)$resource->get('template');
    $isHouse=in_array($template,$pe->ids('house'),true); $isFloor=in_array($template,$pe->ids('floor'),true);
    if (!$isHouse && !$isFloor) return;
    try { $houseId=$isHouse?(int)$resource->get('id'):(int)$pe->houseForFloor($resource->get('id'))->get('id'); }
    catch(Throwable $e) { return; }
    $url=$modx->getOption('assets_url').'components/planpoint/';
    $config=array('houseId'=>$houseId,'floorId'=>$isFloor?(int)$resource->get('id'):0,'sourceId'=>$pe->sourceId(),'connector'=>$url.'connector.php','version'=>'0.2.2-alpha');
    try { $config['mixedImage']=$pe->pickerConfig($houseId); $config['sourceId']=$pe->sourceId(); } catch(Throwable $e) { $config['mixedImage']=null; $modx->log(modX::LOG_LEVEL_ERROR,'[PlanPoint] '.$e->getMessage()); }
    if ($config['mixedImage']) {
        $modx->lexicon->load('mixedimage:default');
        $modx->regClientCSS($modx->getOption('assets_url').'components/mixedimage/css/mgr/mixedimage.css');
        $modx->regClientStartupScript($modx->getOption('assets_url').'components/mixedimage/js/mgr/mixedimage.js');
    }
    $modx->regClientStartupScript($url.'vendor/ace/ace.js');
    $modx->regClientStartupScript($url.'vendor/ace/mode-html.js');
    $modx->regClientStartupScript($url.'vendor/ace/theme-textmate.js');
    $modx->regClientCSS($url.'css/manager.css?v=0.2.2');
    $modx->regClientStartupHTMLBlock('<script>window.PlanPointConfig='.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';</script>');
    $modx->regClientStartupScript($url.'js/geometry.js?v=0.2.2');
    $modx->regClientStartupScript($url.'js/editor.js?v=0.2.2');
    $modx->regClientStartupScript($url.'js/manager.js?v=0.2.2');
}
