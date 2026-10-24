<?php
/** Strict, inert SVG document. v0.1 supports straight path commands only. */
class PlanPointDocument
{
    const MAX_CODE = 1048576;
    public static function parse($code,$checkGeometry=true)
    {
        if (!is_string($code) || strlen($code) > self::MAX_CODE || trim($code) === '') {
            throw new InvalidArgumentException('SVG пуст или превышает 1 МБ.');
        }
        if (preg_match('/<!|<\?|\[\[|\{\$_modx/i', $code)) {
            throw new InvalidArgumentException('DTD, инструкции XML и код MODX/Fenom не поддерживаются.');
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $ok = $doc->loadXML($code, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        if (!$ok || !$doc->documentElement || $doc->documentElement->tagName !== 'svg') {
            throw new InvalidArgumentException('Ожидается корректный корневой элемент svg.');
        }
        $root = $doc->documentElement;
        if ($root->namespaceURI && $root->namespaceURI!=='http://www.w3.org/2000/svg') throw new InvalidArgumentException('Неверное пространство имён SVG.');
        self::attributes($root, array('xmlns', 'viewBox', 'class', 'width', 'height'));
        if ($root->getAttribute('class') !== 'plan-svg__svg') {
            throw new InvalidArgumentException('У svg должен быть class="plan-svg__svg". Код не изменён.');
        }
        $v = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
        if (count($v) !== 4) throw new InvalidArgumentException('Нужен viewBox из четырёх чисел.');
        foreach ($v as $n) if (!is_numeric($n) || !is_finite((float)$n)) throw new InvalidArgumentException('Некорректный viewBox.');
        if ($v[2] <= 0 || $v[3] <= 0) throw new InvalidArgumentException('Размер viewBox должен быть положительным.');
        $areas = array(); $pins = array();
        foreach ($root->childNodes as $node) {
            if ($node instanceof DOMComment) continue;
            if ($node instanceof DOMText && trim($node->textContent) === '') continue;
            if (!($node instanceof DOMElement)) throw new InvalidArgumentException('Неподдержанный узел SVG.');
            if ($node->childNodes->length) throw new InvalidArgumentException('Вложенные элементы не поддерживаются.');
            if ($node->tagName === 'path') {
                self::attributes($node, array('class', 'data-i', 'd'));
                if ($node->getAttribute('class') !== 'path-item') throw new InvalidArgumentException('У path нужен class="path-item".');
                $key = self::key($node->getAttribute('data-i'));
                $d = $node->getAttribute('d'); self::points($d,$checkGeometry);
                $areas[] = array('key' => $key, 'd' => $d);
            } elseif ($node->tagName === 'circle') {
                self::attributes($node, array('data-pin', 'cx', 'cy', 'r'));
                $key = self::key($node->getAttribute('data-pin'));
                if (isset($pins[$key])) throw new InvalidArgumentException('Две точки для одного ключа: '.$key);
                foreach (array('cx','cy','r') as $a) {
                    if (!is_numeric($node->getAttribute($a)) || !is_finite((float)$node->getAttribute($a))) throw new InvalidArgumentException('Некорректная координата точки.');
                }
                if ((float)$node->getAttribute('r') <= 0) throw new InvalidArgumentException('Радиус должен быть положительным.');
                $pins[$key] = array('key'=>$key, 'cx'=>(float)$node->getAttribute('cx'), 'cy'=>(float)$node->getAttribute('cy'), 'r'=>(float)$node->getAttribute('r'));
            } else {
                throw new InvalidArgumentException('Не поддерживается '.$node->tagName.'. Исходная схема сохранена.');
            }
            if (count($areas) + count($pins) > 5000) throw new InvalidArgumentException('Слишком много элементов.');
        }
        return array('svg'=>$code, 'viewbox'=>implode(' ', $v), 'areas'=>$areas, 'pins'=>array_values($pins));
    }
    private static function attributes($node, $allowed)
    {
        foreach ($node->attributes as $a) if (!in_array($a->nodeName, $allowed, true)) {
            throw new InvalidArgumentException('Не поддерживается атрибут '.$a->nodeName.' у '.$node->tagName.'. Код не применён.');
        }
    }
    public static function key($key)
    {
        if (!preg_match('/^[\p{L}\p{N}_-]{1,100}$/u', $key)) throw new InvalidArgumentException('Ключ может содержать только буквы, цифры, _ и -.');
        return $key;
    }
    /** Parse one closed straight subpath. Preserve original d separately. */
    public static function points($d,$checkGeometry=true)
    {
        if (strlen($d) > 100000 || preg_match('/[^MLHVZmlhvz0-9eE+.,\s-]/', $d)) {
            throw new InvalidArgumentException('Версия 0.1 поддерживает один замкнутый путь M/L/H/V/Z. Кривые и составные пути не изменены: импорт отклонён.');
        }
        preg_match_all('/[MLHVZmlhvz]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?/', $d, $m);
        $tokens=$m[0]; $i=0; $cmd=null; $p=array(); $x=0; $y=0; $closed=false;
        while ($i < count($tokens)) {
            if (preg_match('/^[MLHVZ]$/i', $tokens[$i])) {
                $cmd=$tokens[$i++];
                if (strtoupper($cmd)==='Z') { $closed=true; if ($i !== count($tokens)) throw new InvalidArgumentException('Составные пути не поддерживаются.'); break; }
                if (strtoupper($cmd)==='M' && count($p)) throw new InvalidArgumentException('Составные пути не поддерживаются.');
            }
            if ($cmd === null || (!count($p) && strtoupper($cmd)!=='M')) throw new InvalidArgumentException('Путь должен начинаться с M.');
            $count = in_array(strtoupper($cmd),array('M','L'),true) ? 2 : 1;
            $n=array();
            for ($j=0;$j<$count;$j++) {
                if (!isset($tokens[$i]) || !is_numeric($tokens[$i])) throw new InvalidArgumentException('Некорректные координаты path.');
                $n[]=(float)$tokens[$i++];
                if (!is_finite(end($n))) throw new InvalidArgumentException('Неконечная координата.');
            }
            $relative = ctype_lower($cmd);
            switch(strtoupper($cmd)) {
                case 'M': case 'L': $x=$n[0]+($relative?$x:0); $y=$n[1]+($relative?$y:0); break;
                case 'H': $x=$n[0]+($relative?$x:0); break;
                case 'V': $y=$n[0]+($relative?$y:0); break;
            }
            if (!is_finite($x) || !is_finite($y)) throw new InvalidArgumentException('Неконечная координата.');
            $p[]=array($x,$y);
            if ($cmd==='M') $cmd='L'; elseif($cmd==='m') $cmd='l';
            if (count($p)>10000) throw new InvalidArgumentException('Слишком много вершин.');
        }
        if (!$closed || count($p)<3) throw new InvalidArgumentException('Нужен замкнутый контур минимум из трёх вершин.');
        if ($checkGeometry) {
        $distinct=array();foreach($p as $point)$distinct[json_encode($point)]=true;
        if(count($distinct)<3)throw new InvalidArgumentException('Нужны минимум три различные вершины.');
        $first=$p[0];$second=null;$nonlinear=false;
        foreach($p as $point){if($second===null){if($point!==$first)$second=$point;continue;}if(abs(($second[0]-$first[0])*($point[1]-$first[1])-($second[1]-$first[1])*($point[0]-$first[0]))>0){$nonlinear=true;break;}}
        if(!$nonlinear)throw new InvalidArgumentException('Вершины области не должны лежать на одной прямой.');
        }
        // Ensure lexical leftovers aren't silently dropped (e.g. malformed exponent).
        $left=preg_replace('/[MLHVZmlhvz]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?|[\s,]+/', '', $d);
        if ($left !== '') throw new InvalidArgumentException('Некорректный path.');
        return $p;
    }
    public static function emptySvg($width, $height)
    {
        return '<svg class="plan-svg__svg" viewBox="0 0 '.(int)$width.' '.(int)$height.'" xmlns="http://www.w3.org/2000/svg"></svg>';
    }
}
