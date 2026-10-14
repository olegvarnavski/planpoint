<?php
class PlanPointApiProcessor extends modProcessor
{
    public function process()
    {
        if (!$this->modx->user->hasSessionContext('mgr')) return $this->failure('Войдите в менеджер.');
        require_once dirname(__DIR__).'/model/planpoint.php';
        try {
            $pe=new PlanPoint($this->modx); $p=$this->getProperties(); $op=$this->getProperty('op');
            switch($op) {
                case 'houses': $out=$pe->houses(); break;
                case 'list': $out=$pe->listing($p['house_id']); break;
                case 'get': $out=$pe->get($p['id']); break;
                case 'save': $out=$pe->save($p); $pe->refreshCache(array_column($out['assignments'],'floor_id')); break;
                case 'floors': $out=$pe->floors($p['house_id']); break;
                case 'assign':
                    $selected=json_decode($p['selected'],true); $snapshot=json_decode($p['snapshot'],true);
                    if (!is_array($selected) || !is_array($snapshot)) throw new InvalidArgumentException('Некорректные назначения.');
                    foreach($selected as $fid) if (isset($snapshot[$fid]) && $snapshot[$fid] && (int)$snapshot[$fid]['scheme_id']>0 && (int)$snapshot[$fid]['scheme_id']!==(int)$p['id'] && empty($p['confirm_replace'])) throw new RuntimeException('Подтвердите замену схем этажей.');
                    $out=$pe->assign($p['id'],$selected,$snapshot); $pe->refreshCache($out['affected_floor_ids']); break;
                case 'floor-info': $out=$pe->floorInfo($p['floor_id']); break;
                case 'set-floor': $snapshot=json_decode($p['snapshot'],true); $out=$pe->setFloor($p['floor_id'],(int)$p['scheme_id'],$snapshot); $pe->refreshCache(array((int)$p['floor_id'])); break;
                case 'copy': $out=$pe->copy(json_decode($p['ids'],true),$p['target']); break;
                case 'remove': if (empty($p['confirm'])) throw new RuntimeException('Подтвердите удаление.'); $out=$pe->remove($p['id'],$p['revision'],!empty($p['delete_file'])); $pe->refreshCache($out['affected_floor_ids']); break;
                case 'image': $pe->house($p['house_id']); $out=$pe->image($p['path']); break;
                case 'upload': $pe->house($p['house_id']); if (!isset($_FILES['image'])) throw new RuntimeException('Не получен файл.'); $out=$pe->upload($_FILES['image']); break;
                case 'validate': $pe->house($p['house_id']); $out=PlanPointDocument::parse($p['code']); break;
                default: throw new InvalidArgumentException('Неизвестная операция.');
            }
            return $this->success('',array('data'=>$out));
        } catch (InvalidArgumentException $e) { return $this->failure($e->getMessage()); }
        catch (Throwable $e) { $this->modx->log(modX::LOG_LEVEL_ERROR,'[PlanPoint] '.$e->getMessage()); return $this->failure($e->getMessage()); }
    }
}
return 'PlanPointApiProcessor';
