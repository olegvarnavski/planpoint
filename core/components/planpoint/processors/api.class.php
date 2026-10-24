<?php
require_once __DIR__.'/base.class.php';
class PlanPointApiProcessor extends PlanPointProcessorBase
{
    private function value($p,$key,$default=null)
    {
        if (!array_key_exists($key,$p)) { if ($default!==null) return $default; throw new InvalidArgumentException('Не указан параметр '.$key.'.'); }
        if (!is_scalar($p[$key]) || is_bool($p[$key])) throw new InvalidArgumentException('Некорректный параметр '.$key.'.');
        return (string)$p[$key];
    }
    private function number($p,$key,$zero=false)
    {
        $v=$this->value($p,$key);
        if (!preg_match('/^\d{1,10}$/D',$v) || (float)$v>4294967295 || (!$zero && (int)$v===0)) throw new InvalidArgumentException('Некорректный ID '.$key.'.');
        return (int)$v;
    }
    private function json($p,$key,$nullable=false)
    {
        $raw=$this->value($p,$key);if(strlen($raw)>2097152)throw new InvalidArgumentException('Слишком большой параметр '.$key.'.');
        $v=json_decode($raw,true);
        if(json_last_error()!==JSON_ERROR_NONE || (!is_array($v)&&!($nullable&&$v===null)) || (is_array($v)&&count($v)>5000))throw new InvalidArgumentException('Некорректный JSON '.$key.'.');
        return $v;
    }
    private function snapshot($value,$single=false)
    {
        if($value===null)return null;
        $records=$single?array($value):$value;
        foreach($records as$row){if($row===null)continue;if(!is_array($row))throw new InvalidArgumentException('Некорректный снимок назначений.');foreach(array('floor_id','scheme_id','revision','updated_by','updated_at')as$field)if(!array_key_exists($field,$row)||!is_scalar($row[$field]))throw new InvalidArgumentException('Некорректный снимок назначений.');}
        return $value;
    }
    public function process()
    {
        if (!$this->modx->user->hasSessionContext('mgr')) return $this->failure('Войдите в менеджер.');
        require_once dirname(__DIR__).'/model/planpoint.php';
        $requestId=bin2hex(random_bytes(6));$op='unknown';$p=$this->getProperties();
        try {
            $op=$this->value($p,'op');$pe=new PlanPoint($this->modx);$affected=array();
            switch($op) {
                case 'houses': $out=$pe->houses(); break;
                case 'list': $out=$pe->listing($this->number($p,'house_id')); break;
                case 'get': $out=$pe->get($this->number($p,'id')); break;
                case 'save':
                    $data=array('house_id'=>$this->number($p,'house_id'),'title'=>$this->value($p,'title'),'section'=>$this->value($p,'section',''),'image_path'=>$this->value($p,'image_path'),'svg_code'=>$this->value($p,'svg_code'),'show_pins'=>$this->value($p,'show_pins','0')==='1'?1:0,'confirm_used'=>$this->value($p,'confirm_used','0')==='1'?1:0);
                    foreach(array('id','revision','image_source')as$key)if(isset($p[$key]))$data[$key]=$this->number($p,$key,true);
                    if(isset($p['request_key']))$data['request_key']=$this->value($p,'request_key');
                    $out=$pe->save($data);$affected=array_column($out['assignments'],'floor_id');break;
                case 'floors': $out=$pe->floors($this->number($p,'house_id')); break;
                case 'assign':
                    $selected=$this->json($p,'selected');$snapshot=$this->snapshot($this->json($p,'snapshot'));$id=$this->number($p,'id');
                    foreach($selected as$fid){if(!is_int($fid)&&!(is_string($fid)&&ctype_digit($fid)))throw new InvalidArgumentException('Некорректный этаж.');if(isset($snapshot[$fid])&&$snapshot[$fid]&&(int)$snapshot[$fid]['scheme_id']>0&&(int)$snapshot[$fid]['scheme_id']!==$id&&$this->value($p,'confirm_replace','0')!=='1')throw new InvalidArgumentException('Подтвердите замену схем этажей.');}
                    $out=$pe->assign($id,$selected,$snapshot);$affected=$out['affected_floor_ids'];break;
                case 'floor-info': $out=$pe->floorInfo($this->number($p,'floor_id')); break;
                case 'set-floor': $fid=$this->number($p,'floor_id');$out=$pe->setFloor($fid,$this->number($p,'scheme_id',true),$this->snapshot($this->json($p,'snapshot',true),true));$affected=array($fid);break;
                case 'copy': $copyIds=$this->json($p,'ids');foreach($copyIds as$copyId)if(!is_int($copyId)&&!(is_string($copyId)&&ctype_digit($copyId)))throw new InvalidArgumentException('Некорректный ID схемы.');$out=$pe->copy($copyIds,$this->number($p,'target'),isset($p['request_key'])?$this->value($p,'request_key'):null);break;
                case 'remove': if($this->value($p,'confirm','0')!=='1')throw new InvalidArgumentException('Подтвердите удаление.');$out=$pe->remove($this->number($p,'id'),$this->number($p,'revision'),$this->value($p,'delete_file','0')==='1');$affected=$out['affected_floor_ids'];break;
                case 'image': $pe->house($this->number($p,'house_id'));$out=$pe->image($this->value($p,'path'));break;
                case 'upload': $pe->house($this->number($p,'house_id'));if(!isset($_FILES['image'])||!is_array($_FILES['image']))throw new InvalidArgumentException('Не получен файл.');$out=$pe->upload($_FILES['image']);break;
                case 'validate': $pe->house($this->number($p,'house_id'));$out=PlanPointDocument::parse($this->value($p,'code'));break;
                default: throw new InvalidArgumentException('Неизвестная операция.');
            }
            $warnings=$pe->refreshCache($affected);
            if($warnings && is_array($out))$out['cache_warning']='Изменения сохранены, но кеш некоторых этажей не очищен. Код '.$requestId;
            return $this->success('',array('data'=>$out,'request_id'=>$requestId));
        } catch (InvalidArgumentException $e) { return $this->failure($e->getMessage(),array('code'=>'validation','request_id'=>$requestId)); }
        catch (Throwable $e) {
            $this->modx->log(1,'[PlanPoint request '.$requestId.'] op='.$op.' user='.(int)$this->modx->user->get('id').' scheme='.(isset($p['id'])?(int)$p['id']:0).' '.get_class($e).' '.substr($e->getMessage(),0,2000));
            $expected=$e instanceof RuntimeException && !($e instanceof PDOException);
            return $this->failure(($expected?$e->getMessage():'Внутренняя ошибка PlanPoint. Подробности в журнале.').' Код '.$requestId,array('code'=>'operation_failed','request_id'=>$requestId));
        }
    }
}
return 'PlanPointApiProcessor';
