<?php
require_once dirname(__DIR__).'/core/components/planpoint/model/planpoint.php';
require_once dirname(__DIR__).'/core/components/planpoint/model/schema.php';
$count=0;
function ok($value,$name){global $count;if(!$value)throw new RuntimeException('FAIL: '.$name);$count++;echo 'PASS '.$name.PHP_EOL;}
function rejects($fn,$name){try{$fn();}catch(Throwable $e){ok(true,$name);return;}ok(false,$name);}
function svg($inner=''){return '<svg class="plan-svg__svg" viewBox="0 0 1800 840" xmlns="http://www.w3.org/2000/svg">'.$inner.'</svg>';}
$path='<path class="path-item" data-i="x-06" d="M 10 10 h 30 v 30 H 10 Z" />';
$code=svg($path.'<circle data-pin="x-06" cx="20" cy="20" r="0.4"/>');
$d=PlanPointDocument::parse($code);ok($d['svg']===$code,'import preserves exact original SVG');ok($d['areas'][0]['key']==='x-06','leading zero preserved');
$p=PlanPointDocument::points('m 10 10 30 0 v 30 H 10 z');ok($p===array(array(10.0,10.0),array(40.0,10.0),array(40.0,40.0),array(10.0,40.0)),'relative commands and implicit L');
PlanPointDocument::parse(svg($path.$path));ok(true,'repeated area keys allowed');
foreach(array('<script>alert(1)</script>','<g>'.$path.'</g>','<path class="path-item" data-i="x-1" d="M0 0 C1 2 3 4 5 6 Z"/>','<circle data-pin="x-1" cx="0" cy="0" r="0.4" onclick="x()"/>','<foreignObject/>','<path class="path-item" data-i="x-1" d="M0 0 L10 0 L0 10 Z" transform="translate(10)"/>')as$i=>$bad)rejects(function()use($bad){PlanPointDocument::parse(svg($bad));},'unsupported/active SVG rejected #'.$i);
rejects(function(){PlanPointDocument::parse('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>'.svg());},'external entity rejected');
rejects(function(){PlanPointDocument::parse(svg('<!-- [[!evil]] -->'));},'MODX tags in comments rejected');
rejects(function(){PlanPointDocument::points('M0 0 L1 0 L0 1 Z M2 2 L3 3 L2 3 Z');},'compound paths rejected');
rejects(function(){PlanPointDocument::points('M0 0 L1 1');},'open path rejected');
rejects(function(){PlanPointDocument::points('M0 0 L1e999 2 L3 4 Z');},'nonfinite coordinate rejected');
rejects(function(){PlanPointDocument::parse(svg('<circle data-pin="x-1" cx="0" cy="0" r=".4"/><circle data-pin="x-1" cx="1" cy="1" r=".4"/>'));},'duplicate pin rejected');
ok(PlanPointDocument::parse(svg())['areas']===array(),'image-only SVG permitted');
class FakeResource {
 public $clears=0;function clearCache(){$this->clears++;}private $a;function __construct($a){$this->a=$a;}function get($k){return isset($this->a[$k])?$this->a[$k]:null;}function checkPolicy($p){return empty($this->a['denied']);}
}
class FakeUser {function get($k){return 7;}}
class FakeMODX {
 public $pdo;public $user;public $resources=array();
 function __construct(){ $dsn=getenv('PLANPOINT_TEST_DSN')?:'sqlite::memory:';$this->pdo=new PDO($dsn,getenv('PLANPOINT_TEST_USER')?:null,getenv('PLANPOINT_TEST_PASSWORD')?:null);$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->user=new FakeUser();}
 function connect(){return true;}function getCacheManager(){return null;}
 function getOption($k,$options=null,$default=null){$o=array('table_prefix'=>'t_','planpoint.house_templates'=>'1','planpoint.section_templates'=>'2','planpoint.floor_templates'=>'8','planpoint.floor_number_field'=>'menutitle');return isset($o[$k])?$o[$k]:$default;}
 function getObject($class,$criteria){foreach($this->getCollection($class,$criteria)as$r)return $r;return null;}
 function getCollection($class,$criteria){$out=array();foreach($this->resources as $r){$matches=true;foreach($criteria as$k=>$v){if($k==='template:IN'){if(!in_array((int)$r->get('template'),$v,true))$matches=false;}elseif($r->get($k)!=$v)$matches=false;}if($matches)$out[]=$r;}return $out;}
}
$m=new FakeMODX();$driver=$m->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
foreach(array('t_planpoint_assignments','t_planpoint_schemes','t_planpoint_locks')as$table)$m->pdo->exec('DROP TABLE IF EXISTS '.$table);
planpointInstallSchema($m->pdo,'t_',$driver);planpointInstallSchema($m->pdo,'t_',$driver);ok(true,'schema migration repeatable');
function resource($id,$template,$parent,$denied=false){global $m;$m->resources[$id]=new FakeResource(array('id'=>$id,'template'=>$template,'parent'=>$parent,'deleted'=>0,'context_key'=>'web','pagetitle'=>'Ресурс '.$id,'menutitle'=>(string)$id,'denied'=>$denied));}
resource(1,1,0);resource(2,2,1);resource(10,8,2);resource(11,8,1);resource(20,1,0);resource(21,8,20);resource(30,1,0,true);resource(31,8,30);resource(12,8,2,true);
$pe=new PlanPoint($m);ok((int)$pe->houseForFloor(10)->get('id')===1,'house via section');ok((int)$pe->houseForFloor(11)->get('id')===1,'house without section');ok(count($pe->floors(1))===2,'only accessible own floors');
rejects(function()use($pe){$pe->house(30);},'house ACL enforced');
rejects(function()use($pe){$pe->houseForFloor(31);},'floor cannot bypass house ACL');
function insertScheme($house,$title='Test'){global$m,$code;$q=$m->pdo->prepare('INSERT INTO t_planpoint_schemes (house_id,title,image_json,svg_code,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,1,1,?,?)');$q->execute(array($house,$title,json_encode(array('source'=>1,'path'=>'plans/a.png','url'=>'/plans/a.png','width'=>1800,'height'=>840)),$code,'2026-10-14','2026-10-14'));return(int)$m->pdo->lastInsertId();}
$a=insertScheme(1);$b=insertScheme(1);$other=insertScheme(20);
function snapshot($pe){$out=array();foreach($pe->floors(1)as$f)$out[$f['id']]=$f['assignment'];return$out;}
$before=snapshot($pe);$pe->assign($a,array(10),$before);ok(count($pe->get($a)['assignments'])===1,'assign own floor');
rejects(function()use($pe,$a){$pe->assign($a,array(21),snapshot($pe));},'cross-house assignment rejected');
rejects(function()use($pe,$b,$before){$pe->assign($b,array(10),$before);},'stale assignment snapshot rejected');
ok((int)$pe->get($a)['assignments'][0]['floor_id']===10,'failed batch leaves previous assignment');
$copies=$pe->copy(array($a),20);ok(count($copies)===1 && !$copies[0]['assignments'] && $copies[0]['image']['path']==='plans/a.png','copy keeps image, clears assignments');
rejects(function()use($pe,$a){$pe->copy(array($a),30);},'copy target ACL enforced');
rejects(function()use($pe,$a){$pe->remove($a,999,false);},'stale delete rejected');
$result=$pe->remove($a,1,false);ok($result['file_preserved'] && count($m->pdo->query('SELECT * FROM t_planpoint_assignments WHERE scheme_id<>0')->fetchAll())===0,'delete clears assignments and retains shared file');
rejects(function()use($pe,$a){$pe->get($a);},'deleted scheme unavailable');
$now=snapshot($pe);$pe->assign($b,array(10),$now);$stale=snapshot($pe);$pe->assign($b,array(),$stale);
rejects(function()use($pe,$b,$stale){$pe->assign($b,array(10),$stale);},'assign-unassign does not reset revision');
$info=$pe->floorInfo(11);$pe->setFloor(11,$b,$info['assignment']);ok((int)$pe->floorInfo(11)['scheme']['id']===$b,'change one floor');
ok(!$pe->floorInfo(10)['scheme'],'single-floor change leaves other floor unassigned');
rejects(function()use($pe,$other){$pe->setFloor(11,$other,$pe->floorInfo(11)['assignment']);},'single-floor cross-house scheme rejected');
class SaveEditor extends PlanPoint {public function image($path){if($path!=='plans/a.png')throw new InvalidArgumentException('Bad image');return array('source'=>1,'path'=>$path,'url'=>'/plans/a.png','width'=>1800,'height'=>840);}}
$se=new SaveEditor($m);$input=array('house_id'=>1,'title'=>'Новая','section'=>'2','image_path'=>'plans/a.png','svg_code'=>$code,'show_pins'=>1);
$saved=$se->save($input);ok((int)$saved['revision']===1,'create through save');$input['id']=$saved['id'];$input['revision']=1;$input['title']='Изменена';
$saved=$se->save($input);ok((int)$saved['revision']===2,'save increments revision');
rejects(function()use($se,$input){$se->save($input);},'stale save rejected');
$input['revision']=2;$se->assign($saved['id'],array(10),snapshot($se));
rejects(function()use($se,$input){$se->save($input);},'used scheme requires confirmation');
$input['confirm_used']=1;$saved=$se->save($input);ok((int)$saved['revision']===3,'confirmed used scheme saved');
$input['revision']=3;$input['house_id']=20;rejects(function()use($se,$input){$se->save($input);},'save cannot move scheme to another house');
$se->refreshCache(array(10,10,11));ok($m->resources[10]->clears===1 && $m->resources[11]->clears===1,'cache invalidated once per affected floor');
ok($m->resources[1]->clears===0 && $m->resources[21]->clears===0,'unrelated house and floor cache untouched');
ok($se->get($saved['id'])['floor_labels'][0]['number']==='10','assignment labels contain floor number');
ok(count($se->houses())===2,'house selector lists only accessible house templates');
echo 'TOTAL '.$count.PHP_EOL;
