<?php
/** Isolated real-core transport/API test. Never run against a site database. */
if (getenv('PLANPOINT_ALLOW_DB_RESET')!=='1') {fwrite(STDERR,"Set PLANPOINT_ALLOW_DB_RESET=1 for the disposable CI database.\n");exit(2);}
$major=(int)(getenv('MODX_MAJOR')?:2);$source=rtrim(getenv('MODX_SOURCE'),'/');
if (!$source || !is_dir($source))throw new RuntimeException('Set MODX_SOURCE');
$dbName='planpoint_ci_'.$major;
$admin=new PDO(getenv('PLANPOINT_ADMIN_DSN')?:'mysql:host=127.0.0.1;port=13306',getenv('PLANPOINT_TEST_USER')?:'root',getenv('PLANPOINT_TEST_PASSWORD')?:'');
if(getenv('PLANPOINT_UPGRADE')!=='1'){$admin->exec('DROP DATABASE IF EXISTS '.$dbName);$admin->exec('CREATE DATABASE '.$dbName.' CHARACTER SET utf8mb4');}
define('MODX_CORE_PATH',$source.'/core/');define('MODX_BASE_PATH',$source.'/');define('MODX_ASSETS_PATH',$source.'/assets/');define('MODX_BASE_URL','/');define('MODX_ASSETS_URL','/assets/');
if($major===3){require MODX_CORE_PATH.'vendor/autoload.php';class_alias('MODX\\Revolution\\modX','PlanPointTestMODXBase');}else{require MODX_CORE_PATH.'model/modx/modx.class.php';class_alias('modX','PlanPointTestMODXBase');}
class PlanPointTestMODX extends PlanPointTestMODXBase {
 protected function loadConfig($p='',$d=array(),$o=array()) {global $dbName;return array('connections'=>array(array('dsn'=>(getenv('PLANPOINT_ADMIN_DSN')?:'mysql:host=127.0.0.1;port=13306').';dbname='.$dbName.';charset=utf8mb4','username'=>getenv('PLANPOINT_TEST_USER')?:'root','password'=>getenv('PLANPOINT_TEST_PASSWORD')?:'','driverOptions'=>array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_SILENT),'options'=>array())),'table_prefix'=>'modx_','core_path'=>MODX_CORE_PATH,'base_path'=>MODX_BASE_PATH,'assets_path'=>MODX_ASSETS_PATH,'cache_path'=>MODX_CORE_PATH.'cache/planpoint-test/','log_target'=>'ECHO','log_level'=>1,'cache_system_settings'=>false,'cultureKey'=>'en','load_deprecated_global_class_aliases'=>false);}
 public function lexicon($k,$p=array(),$l=''){return $k;}
 public function invokeEvent($e,array $p=array()){return array();}
}

function model($name){return PlanPointPlatform::model($name);}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
if(getenv('PLANPOINT_DEBUG'))fwrite(STDERR,'phase: construct'.PHP_EOL);$modx=new PlanPointTestMODX();$modx->getCacheManager();$modx->connect();$manager=$modx->getManager();$tables=array();
if(getenv('PLANPOINT_UPGRADE')!=='1')foreach(array('modx.mysql.schema.xml','modx.sources.mysql.schema.xml','modx.transport.mysql.schema.xml')as$file){if(!is_file(MODX_CORE_PATH.'model/schema/'.$file))continue;$xml=new DOMDocument();$xml->load(MODX_CORE_PATH.'model/schema/'.$file);$package=rtrim($xml->documentElement->getAttribute('package'),'\\');foreach($xml->getElementsByTagName('object')as$o){$short=$o->getAttribute('class');if(in_array($short,array('modAccess','modAccessibleObject','modAccessibleSimpleObject','modPrincipal')))continue;$class=$major===3?$package.chr(92).$short:(strpos($package,'sources')!==false?'sources.'.$short:(strpos($package,'transport')!==false?'transport.'.$short:$short));$table=$modx->getTableName($class);if(!$table||isset($tables[$table]))continue;$tables[$table]=1;if(!$manager->createObjectContainer($class))throw new RuntimeException('Cannot create core table '.$short);}}
if($major===2){if(getenv('PLANPOINT_UPGRADE')!=='1')foreach(array('sources.modMediaSource','sources.modAccessMediaSource','sources.modMediaSourceElement')as$class)if(!isset($tables[$modx->getTableName($class)]))$manager->createObjectContainer($class);$modx->loadClass('transport.xPDOTransport',XPDO_CORE_PATH,true,true);}
$transportClass=$major===3?'xPDO\\Transport\\xPDOTransport':'xPDOTransport';
$package=getenv('PLANPOINT_PACKAGE');if(!$package||!is_file($package))throw new RuntimeException('Set PLANPOINT_PACKAGE');
@mkdir(MODX_CORE_PATH.'packages/planpoint-test/'.substr(hash_file('sha256',$package),0,12).'/',0755,true);
if(getenv('PLANPOINT_DEBUG'))fwrite(STDERR,'phase: retrieve '.$package.PHP_EOL);$transport=$transportClass::retrieve($modx,$package,MODX_CORE_PATH.'packages/planpoint-test/'.substr(hash_file('sha256',$package),0,12).'/');if(getenv('PLANPOINT_DEBUG'))fwrite(STDERR,'phase: install'.PHP_EOL);check($transport&&$transport->install(),'native transport install');require_once MODX_CORE_PATH.'components/planpoint/model/compat.php';
if(getenv('PLANPOINT_UPGRADE')==='1'){check((int)$modx->pdo->query("SELECT COUNT(*) FROM modx_planpoint_schemes WHERE title='Legacy scheme'")->fetchColumn()===1,'upgrade retains legacy scheme');check((int)$modx->pdo->query("SELECT COUNT(*) FROM modx_planpoint_assignments a INNER JOIN modx_planpoint_schemes s ON s.id=a.scheme_id WHERE s.title='Legacy scheme'")->fetchColumn()===1,'upgrade retains legacy assignment');$setting=$modx->getObject(model('modSystemSetting'),array('key'=>'planpoint.floor_templates'));check($setting->get('value')==='8,18','upgrade preserves custom settings');}
$modx->setOption('planpoint.house_templates','1');$modx->setOption('planpoint.floor_templates','8');$modx->setOption('planpoint.media_source',1);$modx->setOption('planpoint.image_tv','');
$context=$modx->getObject(model('modContext'),'web');if(!$context)$context=$modx->newObject(model('modContext'));$context->set('key','web');$context->save();$modx->context=$context;
$user=$modx->newObject(model('modUser'));$user->fromArray(array('username'=>'planpoint-ci-'.bin2hex(random_bytes(3)),'active'=>1,'sudo'=>1));$user->save();$modx->user=$user;$modx->user->set('sudo',1);
$lexiconClass=model('modLexicon');if($major===2)$modx->getService('lexicon',$lexiconClass);else $modx->lexicon=new $lexiconClass($modx);
$sourceObject=$modx->getObject(model('sources.modFileMediaSource'),1);if(!$sourceObject)$sourceObject=$modx->newObject(model('sources.modFileMediaSource'));$sourceObject->fromArray(array('id'=>1,'name'=>'Test files','class_key'=>model('sources.modFileMediaSource'),'properties'=>array('basePath'=>array('name'=>'basePath','value'=>MODX_BASE_PATH,'type'=>'textfield'),'basePathRelative'=>array('name'=>'basePathRelative','value'=>false,'type'=>'combo-boolean'),'baseUrl'=>array('name'=>'baseUrl','value'=>'/','type'=>'textfield'),'baseUrlRelative'=>array('name'=>'baseUrlRelative','value'=>false,'type'=>'combo-boolean'))),'',true,true);check($sourceObject->save(),'media source saved');
$house=$modx->newObject(model('modDocument'));$house->fromArray(array('pagetitle'=>'House','template'=>1,'context_key'=>'web'));$house->save();
$floor=$modx->newObject(model('modDocument'));$floor->fromArray(array('pagetitle'=>'Floor','menutitle'=>'1','template'=>8,'parent'=>$house->get('id'),'context_key'=>'web'));$floor->save();
@mkdir(MODX_BASE_PATH.'plans',0755,true);copy(__DIR__.'/fixtures/plan.png',MODX_BASE_PATH.'plans/ci.png');
require MODX_CORE_PATH.'components/planpoint/model/planpoint.php';$pe=new PlanPoint($modx);
$input=array('house_id'=>$house->get('id'),'title'=>'Test [[!not-code]]','image_path'=>'plans/ci.png','section'=>'2-A','svg_code'=>PlanPointDocument::emptySvg(800,500),'show_pins'=>0,'request_key'=>'ci_create_123456789');
if(getenv('PLANPOINT_SEED_ONLY')==='1'){$input['title']='Legacy scheme';$legacy=$pe->save($input);$pe->setFloor($floor->get('id'),$legacy['id'],null);$setting=$modx->getObject(model('modSystemSetting'),array('key'=>'planpoint.floor_templates'));$setting->set('value','8,18');$setting->save();echo 'LEGACY SEEDED'.PHP_EOL;exit;}
$s=$pe->save($input);$retry=$pe->save($input);check($s['id']===$retry['id'],'native create idempotency');
$pe->setFloor($floor->get('id'),$s['id'],null);$html=$pe->render($floor->get('id'),'PlanPointScheme','PlanPointEmpty',true);check(strpos($html,'<picture>')===0&&strpos($html,'[[!not-code]]')===false,'native render and escaping');
$modx->user->sessionContexts=array('mgr'=>$user->get('id'));
$response=$modx->runProcessor('api',array('op'=>'get','id'=>$s['id']),array('processors_path'=>MODX_CORE_PATH.'components/planpoint/processors/'));check($response&&!$response->isError(),'native processor');
$invalid=$modx->runProcessor('api',array('op'=>'get','id'=>array('bad')),array('processors_path'=>MODX_CORE_PATH.'components/planpoint/processors/'));check($invalid&&$invalid->isError(),'invalid input rejected');
$apiCreate=array_merge($input,array('op'=>'save','title'=>'API create','image_source'=>1,'request_key'=>'ci_processor_123456789'));
$firstApi=$modx->runProcessor('api',$apiCreate,array('processors_path'=>MODX_CORE_PATH.'components/planpoint/processors/'));$secondApi=$modx->runProcessor('api',$apiCreate,array('processors_path'=>MODX_CORE_PATH.'components/planpoint/processors/'));check(!$firstApi->isError()&&!$secondApi->isError()&&$firstApi->getObject()['data']['id']===$secondApi->getObject()['data']['id'],'native processor create retry');
$modx->setOption('planpoint.media_source',2);$input['id']=$s['id'];$input['revision']=$s['revision'];$input['confirm_used']=1;$saved=$pe->save($input);check((int)$saved['image']['source']===1,'existing image source retained after setting change');
check($transport->install(),'native reinstall');check(count($pe->listing($house->get('id')))===2,'reinstall retains schemes');
if($major===3)check(!class_exists('modX',false)&&!class_exists('modProcessor',false),'no deprecated global class aliases');
echo 'INTEGRATION MODX '.$major.' OK'.PHP_EOL;
