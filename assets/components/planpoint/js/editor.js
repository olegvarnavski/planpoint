(function (root) {
    'use strict';
    var G=root.PlanPointGeometry, NS='http://www.w3.org/2000/svg';
    function el(tag, attrs, parent) { var e=document.createElementNS(NS,tag); Object.keys(attrs||{}).forEach(function(k){e.setAttribute(k,attrs[k]);}); if(parent)parent.appendChild(e); return e; }
    function Editor(container, scheme, onChange, onSelect) {
        this.container=container; this.scheme=scheme; this.onChange=onChange||function(){}; this.onSelect=onSelect||function(){};
        this.history=[];this.future=[];this.drawMode=false;this.ortho=false;this.shiftPressed=false;this.rawCursor=null;this.areas=[]; this.pins=[]; this.selected=-1; this.draft=null; this.drag=null; this.scale=1; this.original=scheme.svg_code; this.changed=false; this.panMode=false; this.spacePressed=false; this.isActive=function(){return true;};
        var doc=new DOMParser().parseFromString(scheme.svg_code,'image/svg+xml');
        if(doc.querySelector('parsererror'))throw Error('Некорректный SVG.');
        this.view=doc.documentElement.getAttribute('viewBox').trim().split(/[\s,]+/).map(Number);
        var self=this;
        doc.querySelectorAll('path').forEach(function(p){self.areas.push({key:p.getAttribute('data-i'),d:p.getAttribute('d'),points:G.points(p.getAttribute('d'))});});
        doc.querySelectorAll('circle[data-pin]').forEach(function(p){self.pins.push({key:p.getAttribute('data-pin'),cx:Number(p.getAttribute('cx')),cy:Number(p.getAttribute('cy')),r:Number(p.getAttribute('r'))});});
        container.innerHTML=''; container.classList.add('pe-canvas');
        this.svg=el('svg',{viewBox:this.view.join(' '),tabindex:0},container);
        this.svg.style.aspectRatio=this.view[2]+'/'+this.view[3];
        this.svg.addEventListener('pointerdown',function(e){self.down(e);});
        this.svg.addEventListener('pointermove',function(e){self.move(e);});
        this.svg.addEventListener('pointerup',function(e){self.drag=null;});
        this.svg.addEventListener('pointercancel',function(){self.drag=null;});
        this.svg.addEventListener('dblclick',function(e){self.addVertex(e);});
        this.svg.addEventListener('wheel',function(e){if(e.ctrlKey){e.preventDefault();self.zoom(e.deltaY<0?1.15:1/1.15);}}, {passive:false});
        this.keyHandler=function(e){
            if(!self.isActive())return;
            var input=/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)||e.target.isContentEditable;
            if(e.key==='Escape'&&(self.drawMode||self.draft||self.drag)){
                e.preventDefault();e.stopImmediatePropagation();self.cancel();return;
            }
            if(input)return;
            if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'){e.preventDefault();e.stopImmediatePropagation();if(e.shiftKey)self.redo();else self.undo();return;}
            if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='y'){e.preventDefault();e.stopImmediatePropagation();self.redo();return;}
            if(e.key==='Shift'){self.shiftPressed=true;self.preview();return;}
            if(e.key===' '){e.preventDefault();self.spacePressed=true;return;}
            if(e.key.toLowerCase()==='n'){e.preventDefault();self.start();}
        };
        this.keyUpHandler=function(e){if(e.key===' ')self.spacePressed=false;if(e.key==='Shift'){self.shiftPressed=false;self.preview();}};
        this.blurHandler=function(){self.spacePressed=false;};
        document.addEventListener('keydown',this.keyHandler,true);document.addEventListener('keyup',this.keyUpHandler);window.addEventListener('blur',this.blurHandler); this.render();
    }
    Editor.prototype.destroy=function(){document.removeEventListener('keydown',this.keyHandler,true);document.removeEventListener('keyup',this.keyUpHandler);window.removeEventListener('blur',this.blurHandler);};
    Editor.prototype.snapshot=function(){return JSON.stringify({areas:this.areas,pins:this.pins,selected:this.selected,draft:this.draft,changed:this.changed,drawMode:this.drawMode});};
    Editor.prototype.remember=function(){this.history.push(this.snapshot());if(this.history.length>100)this.history.shift();this.future=[];};
    Editor.prototype.restore=function(raw){var state=JSON.parse(raw),self=this;Object.keys(state).forEach(function(k){self[k]=state[k];});this.drag=null;this.cursorPoint=null;this.rawCursor=null;this.render();this.onSelect(this.areas[this.selected]||null);this.onChange();};
    Editor.prototype.undo=function(){if(!this.history.length)return;this.future.push(this.snapshot());this.restore(this.history.pop());};
    Editor.prototype.redo=function(){if(!this.future.length)return;this.history.push(this.snapshot());this.restore(this.future.pop());};
    Editor.prototype.cancel=function(){this.drag=null;this.cursorPoint=null;this.rawCursor=null;if(this.draft&&this.draft.length){this.remember();this.draft=[];}else{this.drawMode=false;this.draft=null;this.panMode=false;}this.render();this.onSelect(this.areas[this.selected]||null);};
    Editor.prototype.constrain=function(p,shift){if(!(this.ortho||shift)||!this.draft||!this.draft.length)return p;var last=this.draft[this.draft.length-1];return Math.abs(p[0]-last[0])>=Math.abs(p[1]-last[1])?[p[0],last[1]]:[last[0],p[1]];};
    Editor.prototype.preview=function(){if(!this.draft||!this.draft.length||!this.rawCursor)return;this.cursorPoint=this.constrain(this.rawCursor,this.shiftPressed);var preview=this.svg.querySelector('.pe-draft-preview');if(preview)preview.setAttribute('points',this.draft.concat([this.cursorPoint]).map(function(p){return p.join(',');}).join(' '));var first=this.svg.querySelector('[data-first]');if(first)first.classList.toggle('pe-snap',this.draft.length>=3&&Math.hypot(this.rawCursor[0]-this.draft[0][0],this.rawCursor[1]-this.draft[0][1])<=this.radius()*2);};
    Editor.prototype.touch=function(){this.changed=true;this.onChange();};
    Editor.prototype.xy=function(e){var p=this.svg.createSVGPoint();p.x=e.clientX;p.y=e.clientY;var m=this.svg.getScreenCTM();if(!m)throw Error('Холст не виден.');p=p.matrixTransform(m.inverse());return[p.x,p.y];};
    Editor.prototype.radius=function(){var m=this.svg.getScreenCTM();return m?6/Math.max(Math.abs(m.a),0.0001):4;};
    Editor.prototype.zoom=function(f){this.scale=Math.min(6,Math.max(0.25,this.scale*f));this.svg.style.width=(100*this.scale)+'%';this.svg.style.maxWidth='none';this.render();};
    Editor.prototype.fit=function(){this.scale=1;this.svg.style.width='100%';this.container.scrollLeft=0;this.container.scrollTop=0;this.render();};
    Editor.prototype.selectArea=function(i){if(this.draft&&this.draft.length)return;this.drawMode=false;this.draft=null;this.selected=i;this.render();this.onSelect(this.areas[i]||null);};
    Editor.prototype.start=function(){if(this.draft&&this.draft.length)this.remember();this.panMode=false;this.drawMode=true;this.cursorPoint=null;this.draft=[];this.selected=-1;this.svg.focus();this.render();this.onSelect(null);};
    Editor.prototype.tool=function(mode){if(this.draft&&this.draft.length)this.remember();this.draft=null;this.drawMode=false;this.cursorPoint=null;this.panMode=mode==='pan';if(mode==='draw')this.start();else{this.render();this.onSelect(this.areas[this.selected]||null);}return true;};
    Editor.prototype.close=function(orthogonal){
        if(!this.draft || this.draft.length<3){root.alert('Для замыкания нужны минимум три вершины.');return;}
        this.remember();
        if(orthogonal){var first=this.draft[0],last=this.draft[this.draft.length-1],prev=this.draft[this.draft.length-2];if(Math.abs(first[0]-last[0])>0.00001&&Math.abs(first[1]-last[1])>0.00001)this.draft.push(Math.abs(prev[0]-last[0])>Math.abs(prev[1]-last[1])?[last[0],first[1]]:[first[0],last[1]]);}
        var points=this.draft, number=G.nextNumber(this.areas,this.scheme.section||''), key=G.key(this.scheme.section||'',number);
        this.areas.push({key:key,points:points,d:G.path(points)});this.selected=this.areas.length-1;this.draft=this.drawMode?[]:null;this.cursorPoint=null;
        if(this.scheme.show_pins && !this.pins.some(function(p){return p.key===key;})){var c=G.center(points);this.pins.push({key:key,cx:c[0],cy:c[1],r:0.4});}
        this.touch();this.render();this.onSelect(this.areas[this.selected]);
    };
    Editor.prototype.down=function(e){
        if(e.button===1 || (e.button===0&&(this.panMode||this.spacePressed))){e.preventDefault();this.drag={type:'pan',x:e.clientX,y:e.clientY,left:this.container.scrollLeft,top:this.container.scrollTop};this.svg.setPointerCapture(e.pointerId);return;}
        if(e.button!==0)return;this.svg.focus(); var xy=this.xy(e), t=e.target, self=this;
        if(this.draft){
            if(!this.draft.length){this.selected=-1;this.onSelect(null);}
            if(this.draft.length>=3 && Math.hypot(xy[0]-this.draft[0][0],xy[1]-this.draft[0][1])<=this.radius()*2){this.close(this.ortho||e.shiftKey);return;}
            this.remember();xy=this.constrain(xy,e.shiftKey);this.draft.push(xy);this.cursorPoint=xy;this.onChange();this.render();return;
        }
        if(t.hasAttribute('data-vertex')){this.drag={type:'vertex',index:Number(t.getAttribute('data-vertex'))};this.svg.setPointerCapture(e.pointerId);return;}
        if(t.hasAttribute('data-marker')){this.drag={type:'pin',index:Number(t.getAttribute('data-marker'))};this.svg.setPointerCapture(e.pointerId);return;}
        if(t.hasAttribute('data-area')){this.selected=Number(t.getAttribute('data-area'));this.render();this.onSelect(this.areas[this.selected]);}
    };
    Editor.prototype.move=function(e){
        if(this.draft && this.draft.length){this.rawCursor=this.xy(e);this.shiftPressed=e.shiftKey;this.preview();}
        if(!this.drag)return;
        if(this.drag.type==='pan'){this.container.scrollLeft=this.drag.left+this.drag.x-e.clientX;this.container.scrollTop=this.drag.top+this.drag.y-e.clientY;return;}
        var p=this.xy(e),old=this.drag.type==='vertex'?this.areas[this.selected].points[this.drag.index]:[this.pins[this.drag.index].cx,this.pins[this.drag.index].cy];
        if(Math.hypot(p[0]-old[0],p[1]-old[1])<0.00001)return;if(!this.drag.recorded){this.remember();this.drag.recorded=true;}
        if(this.drag.type==='vertex'){var a=this.areas[this.selected];a.points[this.drag.index]=p;a.d=G.path(a.points);}
        else {var pin=this.pins[this.drag.index];pin.cx=p[0];pin.cy=p[1];}
        this.touch();this.render();
    };
    Editor.prototype.addVertex=function(e){
        if(this.draft || this.selected<0 || e.target.hasAttribute('data-vertex') || e.target.hasAttribute('data-marker'))return;
        var a=this.areas[this.selected],p=this.xy(e),best=Infinity,index=0;
        for(var i=0;i<a.points.length;i++){
            var u=a.points[i],v=a.points[(i+1)%a.points.length],dx=v[0]-u[0],dy=v[1]-u[1],len=dx*dx+dy*dy;
            var t=len?Math.max(0,Math.min(1,((p[0]-u[0])*dx+(p[1]-u[1])*dy)/len)):0;
            var distance=Math.hypot(p[0]-u[0]-t*dx,p[1]-u[1]-t*dy);
            if(distance<best){best=distance;index=i+1;}
        }
        if(best>this.radius()*3)return;this.remember();a.points.splice(index,0,p);a.d=G.path(a.points);this.touch();this.render();
    };
    Editor.prototype.removeVertex=function(index){var a=this.areas[this.selected];if(!a || a.points.length<=3)return;this.remember();a.points.splice(index,1);a.d=G.path(a.points);this.touch();this.render();};
    Editor.prototype.rename=function(key){
        if(this.selected<0)return;if(!/^[\p{L}\p{N}_-]{1,100}$/u.test(key))throw Error('Недопустимый ключ.'); // imported keys stay literal
        var a=this.areas[this.selected],old=a.key;if(old===key)return;this.remember();a.key=key;
        var self=this,oldPin=this.pins.find(function(p){return p.key===old;}),hasOld=this.areas.some(function(p){return p.key===old;}),hasNew=this.pins.some(function(p){return p.key===key;});
        if(!hasOld){this.pins=this.pins.filter(function(p){return p.key!==old;});}
        if(!hasNew && this.scheme.show_pins){var c=oldPin&&!hasOld?[oldPin.cx,oldPin.cy]:G.center(a.points);this.pins.push({key:key,cx:c[0],cy:c[1],r:0.4});}
        this.touch();this.render();
    };
    Editor.prototype.deleteArea=function(){
        if(this.selected<0)return;this.remember();var key=this.areas[this.selected].key;this.areas.splice(this.selected,1);
        if(!this.areas.some(function(a){return a.key===key;}))this.pins=this.pins.filter(function(p){return p.key!==key;});
        this.selected=-1;this.touch();this.render();this.onSelect(null);
    };
    Editor.prototype.movePinToCenter=function(){if(this.selected<0)return;this.remember();var a=this.areas[this.selected],c=G.center(a.points),p=this.pins.find(function(p){return p.key===a.key;});if(!p){p={key:a.key,r:0.4};this.pins.push(p);}p.cx=c[0];p.cy=c[1];this.touch();this.render();};
    Editor.prototype.render=function(){
        var self=this;this.svg.innerHTML='';el('image',{href:this.scheme.image.url,x:this.view[0],y:this.view[1],width:this.view[2],height:this.view[3],preserveAspectRatio:'xMidYMid meet'},this.svg);
        this.areas.forEach(function(a,i){el('path',{d:a.d,'data-area':i,'class':'pe-area'+(i===self.selected?' pe-selected':''),'vector-effect':'non-scaling-stroke'},self.svg);});
        if(this.scheme.show_pins)this.pins.forEach(function(p,i){el('circle',{cx:p.cx,cy:p.cy,r:self.radius()*1.2,'data-marker':i,'class':'pe-marker'},self.svg);el('text',{x:p.cx+self.radius()*2,y:p.cy-self.radius(), 'class':'pe-label','font-size':self.radius()*2.2},self.svg).textContent=p.key.indexOf('x'+(self.scheme.section||'')+'-')===0?p.key.slice(('x'+(self.scheme.section||'')+'-').length):p.key;});
        if(this.selected>=0)this.areas[this.selected].points.forEach(function(p,i){var c=el('circle',{cx:p[0],cy:p[1],r:self.radius(),'data-vertex':i,'class':'pe-vertex'},self.svg);c.addEventListener('contextmenu',function(e){e.preventDefault();self.removeVertex(i);});});
        if(this.draft){if(this.draft.length)el('polyline',{points:this.draft.concat(this.cursorPoint?[this.cursorPoint]:[]).map(function(p){return p.join(',');}).join(' '),'class':'pe-draft-preview','vector-effect':'non-scaling-stroke'},this.svg);if(this.draft.length)el('polyline',{points:this.draft.map(function(p){return p.join(',');}).join(' '),'class':'pe-draft','vector-effect':'non-scaling-stroke'},this.svg);this.draft.forEach(function(p,i){el('circle',{cx:p[0],cy:p[1],r:self.radius(),'class':'pe-vertex',...(i===0?{'data-first':'1'}:{})},self.svg);});}
    };
    Editor.prototype.serialize=function(){
        if(!this.changed)return this.original;
        var doc=new DOMParser().parseFromString(this.original,'image/svg+xml'),svg=doc.documentElement;
        Array.from(svg.children).forEach(function(n){if(n.localName==='path'||n.localName==='circle')svg.removeChild(n);});
        svg.setAttribute('class','plan-svg__svg');svg.setAttribute('viewBox',this.view.join(' '));
        this.areas.forEach(function(a){var p=doc.createElementNS(NS,'path');p.setAttribute('class','path-item');p.setAttribute('data-i',a.key);p.setAttribute('d',a.d);svg.appendChild(p);});
        this.pins.forEach(function(a){var p=doc.createElementNS(NS,'circle');p.setAttribute('data-pin',a.key);p.setAttribute('cx',a.cx);p.setAttribute('cy',a.cy);p.setAttribute('r',a.r);svg.appendChild(p);});
        return new XMLSerializer().serializeToString(svg);
    };
    root.PlanPointCanvas=Editor;
}(window));
