(function (root, factory) {
    var api = factory(); if (typeof module === 'object' && module.exports) module.exports = api; else root.PlanPointGeometry = api;
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';
    function points(d) {
        if (typeof d !== 'string' || /[^MLHVZmlhvz0-9eE+.,\s-]/.test(d)) throw Error('Поддерживается M/L/H/V/Z без кривых.');
        var tokens = d.match(/[MLHVZmlhvz]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?/g) || [];
        var rest = d.replace(/[MLHVZmlhvz]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?|[\s,]+/g, '');
        if (rest) throw Error('Некорректный path.');
        var i=0, cmd=null, out=[], x=0, y=0, closed=false;
        while (i<tokens.length) {
            if (/^[MLHVZ]$/i.test(tokens[i])) {
                cmd=tokens[i++];
                if (cmd.toUpperCase()==='Z') { closed=true; if(i!==tokens.length) throw Error('Составные пути пока не поддерживаются.'); break; }
                if (cmd.toUpperCase()==='M' && out.length) throw Error('Составные пути пока не поддерживаются.');
            }
            if(!cmd || (!out.length && cmd.toUpperCase()!=='M')) throw Error('Нужен M в начале.');
            var n=(/^[ML]$/i.test(cmd))?2:1, v=[];
            for(var j=0;j<n;j++) { if(!tokens[i] || !Number.isFinite(Number(tokens[i]))) throw Error('Некорректные координаты.'); v.push(Number(tokens[i++])); }
            var rel=cmd===cmd.toLowerCase();
            switch(cmd.toUpperCase()) {
                case 'M': case 'L': x=v[0]+(rel?x:0); y=v[1]+(rel?y:0); break;
                case 'H': x=v[0]+(rel?x:0); break;
                case 'V': y=v[0]+(rel?y:0); break;
            }
            if(!Number.isFinite(x)||!Number.isFinite(y)) throw Error('Неконечная координата.');
            out.push([x,y]); if(cmd==='M')cmd='L'; if(cmd==='m')cmd='l';
        }
        if(!closed || out.length<3) throw Error('Нужен замкнутый контур от трёх вершин.');
        return out;
    }
    function path(p) { return p.map(function(a,i){return (i?'L':'M')+' '+a.map(function(n){return String(Math.round(n*100000)/100000);}).join(' ');}).join(' ')+' Z'; }
    function key(section,number) {
        if(!/^[\p{L}\p{N}_-]{1,64}$/u.test(number)) throw Error('Номер: буквы, цифры, _ и -.');
        if(section && !/^[\p{L}\p{N}_-]{1,64}$/u.test(section)) throw Error('Некорректная секция.');
        var result='x'+section+'-'+number;if(result.length>100)throw Error('Итоговый ключ длиннее 100 символов.');return result;
    }
    function inside(p,poly) {
        var c=false; for(var i=0,j=poly.length-1;i<poly.length;j=i++) {
            if(((poly[i][1]>p[1])!==(poly[j][1]>p[1])) && (p[0]<(poly[j][0]-poly[i][0])*(p[1]-poly[i][1])/(poly[j][1]-poly[i][1])+poly[i][0])) c=!c;
        } return c;
    }
    function center(poly) {
        var xs=poly.map(function(p){return p[0];}), ys=poly.map(function(p){return p[1];});
        var minx=Math.min.apply(null,xs), maxx=Math.max.apply(null,xs), miny=Math.min.apply(null,ys), maxy=Math.max.apply(null,ys);
        var c=[(minx+maxx)/2,(miny+maxy)/2]; if(inside(c,poly))return c;
        for(var n=3;n<=65;n+=2) for(var i=1;i<n;i++) for(var j=1;j<n;j++) {
            c=[minx+(maxx-minx)*i/n,miny+(maxy-miny)*j/n]; if(inside(c,poly))return c;
        }
        return poly[0].slice();
    }
    function nextNumber(areas, section) {
        var used=new Set(areas.map(function(a){return a.key;})); var n=1;
        while(used.has('x'+section+'-'+n)) n++; return String(n);
    }
    function floorSummary(floors) {
        if(!floors || !floors.length)return 'Не назначена';
        var groups={};floors.forEach(function(f){var k=f.section||'';if(!groups[k])groups[k]=[];groups[k].push(f);});
        return Object.keys(groups).sort().map(function(group){
            var nums=[],other=[];groups[group].forEach(function(f){var v=String(f.number==null?'':f.number).trim();if(/^-?\d+$/.test(v))nums.push(Number(v));else other.push(v||f.title||('#'+f.id));});
            nums=Array.from(new Set(nums)).sort(function(a,b){return a-b;});var parts=[];
            for(var i=0;i<nums.length;i++){var first=nums[i],last=first;while(i+1<nums.length&&nums[i+1]===last+1)last=nums[++i];parts.push(first===last?String(first):first+'–'+last);}
            return (group?group+': ':'')+'этажи '+parts.concat(other).join(', ');
        }).join(' · ');
    }
    return {points:points,path:path,key:key,inside:inside,center:center,nextNumber:nextNumber,floorSummary:floorSummary};
}));
