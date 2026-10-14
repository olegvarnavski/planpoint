<?php
require_once __DIR__.'/document.php';
class PlanPoint
{
    public $modx; private $mediaContext='web'; private $db; private $s; private $a; private $l;
    public function __construct($modx)
    {
        $this->modx=$modx;
        if (!$modx->connect()) throw new RuntimeException('Не удалось подключиться к базе данных.'); $this->db=$modx->pdo;
        $prefix=$modx->getOption('table_prefix',null,'modx_');
        if (!preg_match('/^[a-zA-Z0-9_]*$/',$prefix)) throw new RuntimeException('Invalid prefix');
        $this->s=$prefix.'planpoint_schemes'; $this->a=$prefix.'planpoint_assignments'; $this->l=$prefix.'planpoint_locks';
    }
    public function ids($kind)
    {
        return array_values(array_filter(array_map('intval',explode(',', $this->modx->getOption('planpoint.'.$kind.'_templates',null,'')))));
    }
    private function query($sql,$values=array())
    {
        $q=$this->db->prepare($sql);
        if (!$q || !$q->execute($values)) throw new RuntimeException('Ошибка базы данных.');
        return $q;
    }
    public function resource($id, $access=true)
    {
        $r=$this->modx->getObject('modResource',array('id'=>(int)$id,'deleted'=>0));
        if (!$r) throw new InvalidArgumentException('Ресурс не найден.');
        if ($access && !$r->checkPolicy('view')) throw new RuntimeException('Нет доступа к ресурсу.');
        return $r;
    }
    public function house($id)
    {
        $r=$this->resource($id);
        if (!in_array((int)$r->get('template'),$this->ids('house'),true)) throw new InvalidArgumentException('Ресурс не является домом. Проверьте настройки шаблонов.');
        $this->mediaContext=$r->get('context_key')?:'web';
        return $r;
    }
    public function houseForFloor($id, $access=true)
    {
        $floor=$this->resource($id,$access);
        if (!in_array((int)$floor->get('template'),$this->ids('floor'),true)) throw new InvalidArgumentException('Ресурс не является этажом.');
        $parent=$this->resource($floor->get('parent'),$access);
        if ($parent->get('context_key')!==$floor->get('context_key')) throw new InvalidArgumentException('Разные контексты.');
        if (in_array((int)$parent->get('template'),$this->ids('house'),true)) return $parent;
        if (!in_array((int)$parent->get('template'),$this->ids('section'),true)) throw new InvalidArgumentException('Не найден подъезд или дом.');
        $house=$this->resource($parent->get('parent'),$access);
        if ($house->get('context_key')!==$floor->get('context_key') || !in_array((int)$house->get('template'),$this->ids('house'),true)) throw new InvalidArgumentException('Не найден дом этажа.');
        return $house;
    }
    public function houses()
    {
        $templates=$this->ids('house'); if (!$templates) return array();
        $out=array();
        foreach($this->modx->getCollection('modResource',array('template:IN'=>$templates,'deleted'=>0)) as $house) {
            if ($house->checkPolicy('view')) $out[]=array('id'=>(int)$house->get('id'),'title'=>$house->get('pagetitle'));
        }
        usort($out,function($a,$b){return strnatcasecmp($a['title'],$b['title']);}); return $out;
    }
    public function floors($houseId)
    {
        $house=$this->house($houseId); $out=array();
        $children=$this->modx->getCollection('modResource',array('parent'=>(int)$houseId,'deleted'=>0,'context_key'=>$house->get('context_key')));
        foreach ($children as $r) {
            if (!$r->checkPolicy('view')) continue;
            if (in_array((int)$r->get('template'),$this->ids('floor'),true)) $out[]=$this->floorData($r,'');
            elseif (in_array((int)$r->get('template'),$this->ids('section'),true)) {
                $fs=$this->modx->getCollection('modResource',array('parent'=>$r->get('id'),'deleted'=>0,'context_key'=>$house->get('context_key')));
                foreach ($fs as $f) if ($f->checkPolicy('view') && in_array((int)$f->get('template'),$this->ids('floor'),true)) $out[]=$this->floorData($f,$r->get('pagetitle'));
            }
        }
        usort($out,function($x,$y){return strnatcmp($x['section'].' '.$x['number'],$y['section'].' '.$y['number']);});
        return $out;
    }
    private function floorData($r,$section)
    {
        $id=(int)$r->get('id'); $a=$this->query("SELECT * FROM {$this->a} WHERE floor_id=?",array($id))->fetch(PDO::FETCH_ASSOC);
        return array('id'=>$id,'title'=>$r->get('pagetitle'),'number'=>$r->get($this->modx->getOption('planpoint.floor_number_field',null,'menutitle')),'section'=>$section,'assignment'=>$a?:null);
    }
    public function get($id, $access=true)
    {
        $s=$this->query("SELECT * FROM {$this->s} WHERE id=?",array((int)$id))->fetch(PDO::FETCH_ASSOC);
        if (!$s) throw new InvalidArgumentException('Схема не найдена.');
        if ($access) $this->house($s['house_id']);
        $s['image']=json_decode($s['image_json'],true);
        $s['assignments']=$this->query("SELECT * FROM {$this->a} WHERE scheme_id=?",array((int)$id))->fetchAll(PDO::FETCH_ASSOC);
        $s['floor_labels']=array();
        foreach($s['assignments'] as $assignment) {
            try {
                $floor=$this->resource($assignment['floor_id'],$access);
                $parent=$this->resource($floor->get('parent'),$access);
                $section=in_array((int)$parent->get('template'),$this->ids('section'),true)?$parent->get('pagetitle'):'';
                $s['floor_labels'][]=array('id'=>(int)$assignment['floor_id'],'number'=>$floor->get($this->modx->getOption('planpoint.floor_number_field',null,'menutitle')),'title'=>$floor->get('pagetitle'),'section'=>$section);
            } catch(Throwable $e) { $s['floor_labels'][]=array('id'=>(int)$assignment['floor_id'],'number'=>'','title'=>'#'.$assignment['floor_id'],'section'=>''); }
        }
        return $s;
    }
    public function listing($id)
    {
        $this->house($id); $out=array();
        $q=$this->query("SELECT id FROM {$this->s} WHERE house_id=? ORDER BY id DESC",array((int)$id));
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $sid) $out[]=$this->get($sid);
        return $out;
    }
    private function transaction($houseId,$fn)
    {
        $this->db->beginTransaction();
        try {
            $mysql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
            $this->query(($mysql?'INSERT IGNORE':'INSERT OR IGNORE')." INTO {$this->l} (house_id) VALUES (0)");
            $this->query("SELECT house_id FROM {$this->l} WHERE house_id=0".($mysql?' FOR UPDATE':''))->fetch();
            $this->query(($mysql?'INSERT IGNORE':'INSERT OR IGNORE')." INTO {$this->l} (house_id) VALUES (?)",array((int)$houseId));
            $this->query("SELECT house_id FROM {$this->l} WHERE house_id=?".($mysql?' FOR UPDATE':''),array((int)$houseId))->fetch();
            $out=$fn(); $this->db->commit(); return $out;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function revision($s,$value)
    {
        if ((int)$value!==(int)$s['revision']) throw new RuntimeException('Схема уже изменена другим сотрудником. Обновите список.');
    }
    public function save($data)
    {
        $houseId=(int)$data['house_id']; $this->house($houseId);
        $id=isset($data['id'])?(int)$data['id']:0;
        $title=trim(isset($data['title'])?$data['title']:'');
        if ($title==='' || mb_strlen($title)>255) throw new InvalidArgumentException('Название обязательно, максимум 255 символов.');
        $section=trim(isset($data['section'])?$data['section']:'');
        if ($section!=='' && !preg_match('/^[\p{L}\p{N}_-]{1,64}$/u',$section)) throw new InvalidArgumentException('Некорректная секция.');
        $doc=PlanPointDocument::parse($data['svg_code']);
        $image=$this->image($data['image_path']); // server authoritative, never trusts posted URL/dimensions
        return $this->transaction($houseId,function() use($data,$id,$houseId,$title,$section,$doc,$image) {
            $image=$this->image($data['image_path']); // recheck under global reference lock
            $user=(int)$this->modx->user->get('id'); $now=date('Y-m-d H:i:s');
            $values=array($title,$section,json_encode($image,JSON_UNESCAPED_UNICODE),$doc['svg'],empty($data['show_pins'])?0:1,$user,$now);
            if ($id) {
                $s=$this->get($id); if ((int)$s['house_id']!==$houseId) throw new InvalidArgumentException('Дом схемы нельзя менять сохранением.');
                $this->revision($s,isset($data['revision'])?$data['revision']:0);
                if (count($s['assignments']) && empty($data['confirm_used'])) throw new RuntimeException('Подтвердите изменение схемы назначенных этажей.');
                $values[]=$id; $values[]=(int)$s['revision'];
                $this->query("UPDATE {$this->s} SET title=?,section=?,image_json=?,svg_code=?,show_pins=?,updated_by=?,updated_at=?,revision=revision+1 WHERE id=? AND revision=?",$values);
            } else {
                $values[]=$houseId; $values[]=$user; $values[]=$now;
                $this->query("INSERT INTO {$this->s} (title,section,image_json,svg_code,show_pins,updated_by,updated_at,house_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)",$values);
                $id=(int)$this->db->lastInsertId();
            }
            return $this->get($id);
        });
    }
    public function assign($schemeId,$selected,$snapshot,$confirmed=false)
    {
        $s=$this->get($schemeId); $houseId=(int)$s['house_id'];
        $available=$this->floors($houseId); $map=array(); foreach($available as $f) $map[(int)$f['id']]=$f;
        $selected=array_values(array_unique(array_map('intval',$selected)));
        foreach($selected as $fid) if (!isset($map[$fid])) throw new InvalidArgumentException('Этаж не принадлежит дому или недоступен.');
        return $this->transaction($houseId,function()use($schemeId,$selected,$snapshot,$map){
            $this->get($schemeId); // deletion may have happened before acquiring lock
            foreach($map as $fid=>$f) {
                $current=$this->query("SELECT * FROM {$this->a} WHERE floor_id=?",array($fid))->fetch(PDO::FETCH_ASSOC);
                $before=isset($snapshot[(string)$fid])?$snapshot[(string)$fid]:null;
                if (json_encode($current?:null)!==json_encode($before?:null)) throw new RuntimeException('Назначения изменились. Откройте окно заново.');
            }
            foreach($map as $fid=>$f) {
                $this->query("UPDATE {$this->a} SET scheme_id=0,revision=revision+1,updated_by=?,updated_at=? WHERE floor_id=? AND scheme_id=?",array((int)$this->modx->user->get('id'),date('Y-m-d H:i:s'),$fid,(int)$schemeId));
            }
            foreach($selected as $fid) {
                $before=isset($snapshot[(string)$fid])?$snapshot[(string)$fid]:null;
                $this->query("DELETE FROM {$this->a} WHERE floor_id=?",array($fid));
                $this->query("INSERT INTO {$this->a} (floor_id,scheme_id,revision,updated_by,updated_at) VALUES (?,?,?,?,?)",array($fid,(int)$schemeId,$before?(int)$before['revision']+1:1,(int)$this->modx->user->get('id'),date('Y-m-d H:i:s')));
            }
            
            $affected=$selected;
            foreach($snapshot as $fid=>$assignment) if (isset($map[(int)$fid]) && $assignment && (int)$assignment['scheme_id']===(int)$schemeId) $affected[]=(int)$fid;
            return array('assigned'=>count($selected),'affected_floor_ids'=>array_values(array_unique($affected)));
        });
    }
    public function floorInfo($floorId)
    {
        $house=$this->houseForFloor($floorId);
        $row=$this->query("SELECT * FROM {$this->a} WHERE floor_id=?",array((int)$floorId))->fetch(PDO::FETCH_ASSOC);
        $scheme=null;
        if ($row && (int)$row['scheme_id']) {
            try { $candidate=$this->get($row['scheme_id']); if ((int)$candidate['house_id']===(int)$house->get('id')) $scheme=$candidate; } catch(InvalidArgumentException $e) {}
        }
        return array('house_id'=>(int)$house->get('id'),'floor_id'=>(int)$floorId,'assignment'=>$row?:null,'scheme'=>$scheme);
    }
    public function setFloor($floorId,$schemeId,$snapshot)
    {
        $house=$this->houseForFloor($floorId); $houseId=(int)$house->get('id');
        return $this->transaction($houseId,function()use($floorId,$schemeId,$snapshot,$houseId){
            if ($schemeId) { $s=$this->get($schemeId); if ((int)$s['house_id']!==$houseId) throw new InvalidArgumentException('Схема другого дома.'); }
            $current=$this->query("SELECT * FROM {$this->a} WHERE floor_id=?",array((int)$floorId))->fetch(PDO::FETCH_ASSOC);
            if (json_encode($current?:null)!==json_encode($snapshot?:null)) throw new RuntimeException('Назначение этажа изменилось. Обновите данные.');
            $this->query("DELETE FROM {$this->a} WHERE floor_id=?",array((int)$floorId));
            $this->query("INSERT INTO {$this->a} (floor_id,scheme_id,revision,updated_by,updated_at) VALUES (?,?,?,?,?)",array((int)$floorId,(int)$schemeId,$current?(int)$current['revision']+1:1,(int)$this->modx->user->get('id'),date('Y-m-d H:i:s')));
            return $this->floorInfo($floorId);
        });
    }
    public function copy($ids,$target)
    {
        $this->house($target); $ids=array_values(array_unique(array_map('intval',$ids)));
        if (!$ids || count($ids)>100) throw new InvalidArgumentException('Выберите от 1 до 100 схем.');
        foreach($ids as $id) $this->get($id);
        return $this->transaction($target,function()use($ids,$target){
            $out=array(); foreach($ids as $id) {
                $s=$this->get($id); $now=date('Y-m-d H:i:s'); $u=(int)$this->modx->user->get('id');
                $this->query("INSERT INTO {$this->s} (house_id,title,section,image_json,svg_code,show_pins,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)",array((int)$target,mb_substr('Копия '.$s['title'],0,255),$s['section'],$s['image_json'],$s['svg_code'],$s['show_pins'],$u,$u,$now,$now));
                $out[]=$this->get((int)$this->db->lastInsertId());
            } return $out;
        });
    }
    public function remove($id,$revision,$deleteFile)
    {
        $s=$this->get($id);
        $result=$this->transaction($s['house_id'],function()use($id,$revision){
            $s=$this->get($id); $this->revision($s,$revision);
            $this->query("UPDATE {$this->a} SET scheme_id=0,revision=revision+1 WHERE scheme_id=?",array((int)$id));
            $this->query("DELETE FROM {$this->s} WHERE id=?",array((int)$id));
            return array('deleted'=>true,'file_preserved'=>true,'affected_floor_ids'=>array_map(function($a){return (int)$a['floor_id'];},$s['assignments']),'message'=>'Схема и назначения удалены. Изображение сохранено.');
        });
        if (!$deleteFile) return $result;
        try {
            return $this->transaction($s['house_id'],function()use($s,$result){
                $count=0;
                foreach($this->query("SELECT image_json FROM {$this->s}")->fetchAll(PDO::FETCH_COLUMN) as $raw) {
                    $ref=json_decode($raw,true);
                    if ($ref['source']==$s['image']['source'] && $ref['path']===$s['image']['path']) $count++;
                }
                if ($count) { $result['message']='Схема удалена. Изображение сохранено: используется ещё в '.$count.' схемах.'; return $result; }
                if ((int)$s['image']['source']!==$this->sourceId()) { $result['message']='Схема удалена. Изображение сохранено: источник изменился.'; return $result; }
                $folder=trim($this->modx->getOption('planpoint.image_folder',null,'plans'),'/').'/';
                if (strpos($s['image']['path'],$folder)!==0) { $result['message']='Схема удалена. Изображение вне папки модуля сохранено.'; return $result; }
                $this->image($s['image']['path']);
                $source=modMediaSource::getDefaultSource($this->modx,(int)$s['image']['source'],false); $source->initialize();
                if (!$source->checkPolicy('remove') || !$source->removeObject($s['image']['path'])) throw new RuntimeException('Источник не разрешил удалить изображение.');
                $result['file_preserved']=false; $result['message']='Схема, назначения и изображение удалены.'; return $result;
            });
        } catch(Throwable $e) { $this->modx->log(modX::LOG_LEVEL_ERROR,'[PlanPoint file removal] '.$e->getMessage()); $result['message']='Схема удалена, изображение сохранено: '.$e->getMessage(); return $result; }
    }
    public function imageTV()
    {
        $name=trim($this->modx->getOption('planpoint.image_tv',null,'imgMigx'));
        return $name?$this->modx->getObject('modTemplateVar',array('name'=>$name)):null;
    }
    public function sourceId()
    {
        $configured=(int)$this->modx->getOption('planpoint.media_source',null,0);
        if ($configured) return $configured;
        $tv=$this->imageTV();
        if ($tv) { $source=$tv->getSource($this->mediaContext); if ($source) return (int)$source->get('id'); }
        return 1;
    }
    public function pickerConfig($houseId)
    {
        $house=$this->house($houseId); $tv=$this->imageTV();
        if (!$tv || !is_file(MODX_ASSETS_PATH.'components/mixedimage/js/mgr/mixedimage.js')) return null;
        $opts=$tv->get('input_properties');
        if (!is_array($opts)) $opts=@unserialize($opts,array('allowed_classes'=>false));
        if (!is_array($opts)) $opts=array();
        $source=$tv->getSource($this->mediaContext);
        if (!$source || !$source->initialize() || !$source->checkPolicy('view')) throw new RuntimeException('Источник TV недоступен.');
        if ((int)$source->get('id')!==$this->sourceId()) throw new RuntimeException('Источник PlanPoint отличается от источника TV. Укажите planpoint.media_source = 0.');
        $path=str_replace(array('{id}','{pid}'),array($houseId,$house->get('parent')),isset($opts['path'])?$opts['path']:'');
        return array('tv_id'=>(int)$tv->get('id'),'source'=>$this->sourceId(),'ms_id'=>$this->sourceId(),'res_id'=>(int)$houseId,'res_alias'=>$house->get('alias'),'p_id'=>(int)$house->get('parent'),'p_alias'=>'','ctx'=>$this->mediaContext,'ctx_path'=>$source->getBaseUrl(),'openPath'=>$path,'acceptedMIMEtypes'=>'image/png,image/jpeg','prefixFilename'=>!empty($opts['prefixFilename'])&&in_array(strtolower((string)$opts['prefixFilename']),array('1','true','yes','да',strtolower($this->modx->lexicon('yes'))),true),'triggerlist'=>'clear,manager,pc,url','removeFile'=>false,'showPreview'=>false,'onlyEdit'=>true);
    }
    public function image($path)
    {
        $sourceId=$this->sourceId();
        $this->modx->loadClass('sources.modMediaSource');
        $source=modMediaSource::getDefaultSource($this->modx,$sourceId,false);
        if (!$source || !$source->initialize() || !$source->checkPolicy('view')) throw new RuntimeException('Источник файлов недоступен.');
        if ($source->get('class_key')!=='sources.modFileMediaSource') throw new InvalidArgumentException('Первая альфа поддерживает файловый источник MODX.');
        $path=trim($path); $public=$source->getObjectUrl('');
        if ($public && strpos($path,$public)===0) $path=substr($path,strlen($public));
        $path=ltrim(str_replace('\\','/',trim($path)),'/');
        if (!$path || !preg_match('/\.(?:png|jpe?g)$/i',$path) || preg_match('/(^|\/)\.\.?($|\/)|[\x00-\x1f]|[:?#]/',$path)) throw new InvalidArgumentException('Недопустимый путь изображения.');
        $base=realpath($source->getBasePath()); $real=realpath($source->getBasePath().$path);
        if (!$base || !$real || strpos($real,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($real)) throw new InvalidArgumentException('Файл вне источника или не найден.');
        $info=@getimagesize($real);
        if (!$info || !in_array($info[2],array(IMAGETYPE_PNG,IMAGETYPE_JPEG),true) || filesize($real)>20*1024*1024) throw new InvalidArgumentException('Нужен PNG/JPG до 20 МБ.');
        $url=$source->getObjectUrl($path);
        if (preg_match('/[\[\]{}]/',$url) || !preg_match('~^(?:https?://|/)~i',$url)) throw new InvalidArgumentException('Источник должен иметь безопасный публичный URL.');
        return array('source'=>$sourceId,'path'=>$path,'url'=>$url,'width'=>$info[0],'height'=>$info[1]);
    }
    public function upload($file)
    {
        $this->modx->loadClass('sources.modMediaSource');
        $source=modMediaSource::getDefaultSource($this->modx,$this->sourceId(),false);
        if (!$source || !$source->initialize() || !$source->checkPolicy('create') || $source->get('class_key')!=='sources.modFileMediaSource') throw new RuntimeException('Нет права загрузки в файловый источник.');
        if ($file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size']>20*1024*1024) throw new InvalidArgumentException('Ошибка загрузки или файл больше 20 МБ.');
        $info=@getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2],array(IMAGETYPE_PNG,IMAGETYPE_JPEG),true)) throw new InvalidArgumentException('Нужен PNG или JPG.');
        $folder=trim($this->modx->getOption('planpoint.image_folder',null,'plans'),'/');
        if (!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~',$folder)) throw new InvalidArgumentException('Некорректная папка схем в настройках.');
        $base=realpath($source->getBasePath()); if (!$base) throw new RuntimeException('Не найден путь источника.');
        $cursor=$base;
        foreach(explode('/',$folder) as $part) { $cursor.='/'.$part; if (!is_dir($cursor) && !mkdir($cursor,0755)) throw new RuntimeException('Не удалось создать папку.'); if (strpos(realpath($cursor),$base.DIRECTORY_SEPARATOR)!==0) throw new RuntimeException('Папка вне источника.'); }
        $name=bin2hex(random_bytes(16)).($info[2]===IMAGETYPE_PNG?'.png':'.jpg');
        if (!$source->checkFiletype($name)) throw new RuntimeException('Тип файла запрещён настройками источника.');
        if (!move_uploaded_file($file['tmp_name'],$cursor.'/'.$name)) throw new RuntimeException('Не удалось сохранить файл.');
        return $this->image($folder.'/'.$name);
    }
    public function render($floorId,$tpl,$empty,$inner=false)
    {
        try {
            $house=$this->houseForFloor($floorId,false);
            $row=$this->query("SELECT scheme_id FROM {$this->a} WHERE floor_id=?",array((int)$floorId))->fetch(PDO::FETCH_ASSOC);
            if (!$row || !(int)$row['scheme_id']) return $inner?'':$this->modx->getChunk($empty);
            $s=$this->get($row['scheme_id'],false);
            if ((int)$s['house_id']!==(int)$house->get('id')) return $inner?'':$this->modx->getChunk($empty);
            $doc=PlanPointDocument::parse($s['svg_code']); $svg=$s['svg_code'];
            if (!$s['show_pins']) { $d=new DOMDocument(); $d->loadXML($svg,LIBXML_NONET); foreach(iterator_to_array($d->getElementsByTagName('circle')) as $n) $n->parentNode->removeChild($n); $svg=$d->saveXML($d->documentElement); }
            $esc=function($v){return str_replace(array('[',']','{','}'),array('&#91;','&#93;','&#123;','&#125;'),htmlspecialchars($v,ENT_QUOTES,'UTF-8'));};
            if ($inner) return '<picture><img class="plan-svg__img" src="'.$esc($s['image']['url']).'" alt="'.$esc($s['title']).'" width="'.(int)$s['image']['width'].'" height="'.(int)$s['image']['height'].'"></picture>'.$svg;
            return $this->modx->getChunk($tpl,array('scheme_id'=>$s['id'],'house_id'=>$s['house_id'],'floor_id'=>(int)$floorId,'title'=>$esc($s['title']),'image_url'=>$esc($s['image']['url']),'image_width'=>$s['image']['width'],'image_height'=>$s['image']['height'],'viewbox'=>$doc['viewbox'],'svg'=>$svg));
        } catch(Throwable $e) { $this->modx->log(modX::LOG_LEVEL_ERROR,'[PlanPoint] '.$e->getMessage()); return $inner?'':$this->modx->getChunk($empty); }
    }
    public function refreshCache($floorIds)
    {
        // A scheme affects floor pages only. Never clean lexicons/scripts/menu or fire global OnCacheUpdate.
        $this->modx->getCacheManager();
        foreach(array_unique(array_map('intval',$floorIds)) as $id) {
            if ($id<=0) continue;
            try {
                $resource=$this->modx->getObject('modResource',array('id'=>$id));
                if ($resource) $resource->clearCache();
            } catch(Throwable $e) { $this->modx->log(modX::LOG_LEVEL_ERROR,'[PlanPoint cache #'.$id.'] '.$e->getMessage()); }
        }
    }
}
