<?php
/** Explicit platform mappings; no dependency on MODX 3 deprecated global aliases. */
class PlanPointPlatform
{
    public static function isV3() { return class_exists('MODX\\Revolution\\modX'); }
    public static function model($name)
    {
        if (!self::isV3()) return $name;
        $sources=array('sources.modMediaSource'=>'MODX\\Revolution\\Sources\\modMediaSource','sources.modFileMediaSource'=>'MODX\\Revolution\\Sources\\modFileMediaSource');
        return isset($sources[$name])?$sources[$name]:'MODX\\Revolution\\'.$name;
    }
    public static function source($modx,$id)
    {
        if (!self::isV3()) $modx->loadClass('sources.modMediaSource');
        $class=self::isV3()?self::model('sources.modMediaSource'):'modMediaSource';
        return $class::getDefaultSource($modx,(int)$id,false);
    }
}
