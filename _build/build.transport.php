<?php
/** php _build/build.transport.php /absolute/site/config.core.php */
if (!isset($modx)) {
    if (empty($argv[1]) || !is_file($argv[1])) { fwrite(STDERR,"Usage: php _build/build.transport.php /path/to/site/config.core.php\n"); exit(1); }
    require $argv[1];
    if(is_file(MODX_CORE_PATH.'vendor/autoload.php'))require_once MODX_CORE_PATH.'vendor/autoload.php';else require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modxClass=class_exists('MODX\\Revolution\\modX')?'MODX\\Revolution\\modX':'modX';
    $modx=new $modxClass(); $modx->initialize('mgr');
}
$root=dirname(__DIR__).'/';
require_once $root.'core/components/planpoint/model/compat.php';
$isV3=PlanPointPlatform::isV3();
if(!$isV3)$modx->loadClass('transport.modPackageBuilder','',false,true);
$builderClass=$isV3?'MODX\\Revolution\\Transport\\modPackageBuilder':'modPackageBuilder';
$planpointTransportClass=$isV3?'xPDO\\Transport\\xPDOTransport':'xPDOTransport';
$builder=isset($builder)?$builder:new $builderClass($modx);
$builder->createPackage('planpoint','0.3.0','alpha');
$namespace=$modx->newObject(PlanPointPlatform::model('modNamespace')); $namespace->fromArray(array('name'=>'planpoint','path'=>'{core_path}components/planpoint/','assets_path'=>'{assets_path}components/planpoint/'),'',true,true);
$builder->registerNamespace($namespace,false,true);
$settings=array('house_templates'=>'','section_templates'=>'','floor_templates'=>'8','media_source'=>'0','image_tv'=>'imgMigx','image_folder'=>'plans','floor_number_field'=>'menutitle','modx3_mixedimage'=>'0');
foreach($settings as $key=>$value) {
    $o=$modx->newObject(PlanPointPlatform::model('modSystemSetting')); $o->fromArray(array('key'=>'planpoint.'.$key,'value'=>$value,'xtype'=>$key==='modx3_mixedimage'?'combo-boolean':($key==='media_source'?'numberfield':'textfield'),'namespace'=>'planpoint','area'=>'planpoint'),'',true,true);
    $v=$builder->createVehicle($o,array($planpointTransportClass::UNIQUE_KEY=>'key',$planpointTransportClass::PRESERVE_KEYS=>true,$planpointTransportClass::UPDATE_OBJECT=>false));
    if(!$builder->putVehicle($v))throw new RuntimeException('Cannot package setting');
}
$category=$modx->newObject(PlanPointPlatform::model('modCategory')); $category->set('category','PlanPoint');
$snippet=$modx->newObject(PlanPointPlatform::model('modSnippet'));$snippet->fromArray(array('name'=>'PlanPointRender','description'=>'Назначенная схема текущего этажа.','snippet'=>preg_replace('/^<\?php\s*/','',file_get_contents($root.'core/components/planpoint/snippets/render.php'))));
$plugin=$modx->newObject(PlanPointPlatform::model('modPlugin'));$plugin->fromArray(array('name'=>'PlanPointManager','description'=>'Вкладка Планировки у дома и этажа.','plugincode'=>preg_replace('/^<\?php\s*/','',file_get_contents($root.'core/components/planpoint/plugins/manager.php'))));
$events=array();foreach(array('OnDocFormPrerender')as$name){$e=$modx->newObject(PlanPointPlatform::model('modPluginEvent'));$e->fromArray(array('event'=>$name,'priority'=>0,'propertyset'=>0),'',true,true);$events[]=$e;}$plugin->addMany($events,'PluginEvents');
$chunks=array();foreach(array('PlanPointScheme'=>'scheme','PlanPointEmpty'=>'empty')as$name=>$file){$o=$modx->newObject(PlanPointPlatform::model('modChunk'));$o->fromArray(array('name'=>$name,'snippet'=>file_get_contents($root.'core/components/planpoint/chunks/'.$file.'.tpl')));$chunks[]=$o;}
$snippets=array($snippet);$plugins=array($plugin);$category->addMany($snippets,'Snippets');$category->addMany($plugins,'Plugins');$category->addMany($chunks,'Chunks');
$attr=array($planpointTransportClass::UNIQUE_KEY=>'category',$planpointTransportClass::PRESERVE_KEYS=>false,$planpointTransportClass::UPDATE_OBJECT=>true,$planpointTransportClass::RELATED_OBJECTS=>true,$planpointTransportClass::RELATED_OBJECT_ATTRIBUTES=>array(
 'Snippets'=>array($planpointTransportClass::UNIQUE_KEY=>'name',$planpointTransportClass::PRESERVE_KEYS=>false,$planpointTransportClass::UPDATE_OBJECT=>true),
 'Chunks'=>array($planpointTransportClass::UNIQUE_KEY=>'name',$planpointTransportClass::PRESERVE_KEYS=>false,$planpointTransportClass::UPDATE_OBJECT=>false),
 'Plugins'=>array($planpointTransportClass::UNIQUE_KEY=>'name',$planpointTransportClass::PRESERVE_KEYS=>false,$planpointTransportClass::UPDATE_OBJECT=>true,$planpointTransportClass::RELATED_OBJECTS=>true,$planpointTransportClass::RELATED_OBJECT_ATTRIBUTES=>array('PluginEvents'=>array($planpointTransportClass::UNIQUE_KEY=>array('pluginid','event'),$planpointTransportClass::PRESERVE_KEYS=>true,$planpointTransportClass::UPDATE_OBJECT=>true)))
));
$v=$builder->createVehicle($category,$attr);
$v->validate('php',array('source'=>$root.'_build/validator.php'));
$v->resolve('file',array('source'=>$root.'core/components/planpoint','target'=>"return MODX_CORE_PATH . 'components/';"));
$v->resolve('file',array('source'=>$root.'assets/components/planpoint','target'=>"return MODX_ASSETS_PATH . 'components/';"));
$v->resolve('php',array('source'=>$root.'_build/resolver.php'));
if(!$builder->putVehicle($v))throw new RuntimeException('Cannot package category');
$builder->setPackageAttributes(array('readme'=>file_get_contents($root.'README.md'),'license'=>'Copyright 2026. Created for the project owner.','requires'=>array('php'=>$isV3?'>=8.1':'>=7.4','modx'=>$isV3?'>=3.2.0 <3.3.0':'>=2.8.0 <2.9.0')));
if(!$builder->pack())throw new RuntimeException('Package build failed');
echo $builder->directory.$builder->filename.PHP_EOL;
