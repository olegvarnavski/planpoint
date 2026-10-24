(function(){
'use strict';
var knownSchemeTitles={},C=window.PlanPointConfig, E=Ext.util.Format.htmlEncode, G=window.PlanPointGeometry;
if(!C)return;
function requestKey(){var a=new Uint8Array(16);window.crypto.getRandomValues(a);return Array.from(a).map(function(n){return ('0'+n.toString(16)).slice(-2);}).join('');}
function fail(message){Ext.Msg.alert('PlanPoint',E(String(message)));}
function api(op,params,done,onFailure){
    Ext.Ajax.request({url:C.connector,method:'POST',params:Ext.apply({action:'api',op:op,HTTP_MODAUTH:MODx.siteId},params||{}),
        success:function(r){try{var a=Ext.decode(r.responseText);if(!a.success){if(onFailure)onFailure();fail(a.message||'Ошибка запроса');return;}if(a.object.data&&a.object.data.cache_warning)MODx.msg.status({title:'PlanPoint',message:E(a.object.data.cache_warning)});done(a.object.data);}catch(e){if(onFailure)onFailure();fail(e.message);}},
        failure:function(){if(onFailure)onFailure();fail('Сервер недоступен. Данные остаются в открытом редакторе; сохранение на сервер не подтверждено.');}});
}
function field(label,value){return new Ext.form.TextField({fieldLabel:label,value:value||'',anchor:'96%'});}
var imageBrowser=null,imageCallback=null;
function chooseImage(form,callback){
    imageCallback=callback;
    if(!imageBrowser)imageBrowser=MODx.load({xtype:'modx-browser',source:C.sourceId,multiple:false,allowedFileTypes:'png,jpg,jpeg',listeners:{select:function(data){
        if(!data)return;var path=data.pathRelative||data.relativeUrl||data.url;
        api('image',{house_id:C.houseId,path:path},function(image){if(imageCallback)imageCallback(image);imageBrowser.hide();});
    }}});
    if(imageBrowser&&imageBrowser.show)imageBrowser.show();
}
function upload(callback){
    var input=document.createElement('input');input.type='file';input.accept='image/png,image/jpeg';
    input.addEventListener('change',function(){if(!input.files[0])return;var f=new FormData();f.append('image',input.files[0]);f.append('action','api');f.append('op','upload');f.append('house_id',C.houseId);f.append('HTTP_MODAUTH',MODx.siteId);
        fetch(C.connector,{method:'POST',body:f,credentials:'same-origin',headers:{modAuth:MODx.siteId}}).then(function(r){return r.json();}).then(function(a){if(!a.success)throw Error(a.message);callback(a.object.data);}).catch(function(e){fail(e.message);});
    });input.click();
}
function imagePicker(current,callback){
    var selectedImage=current,picker=null,uid='pp-'+Ext.id(),pending=0,validating=false;
    var host=new Ext.Panel({border:false,bodyStyle:'padding:18px',html:'<div id="'+uid+'"></div>'});
    function accept(value){var request=++pending;validating=false;if(!value){selectedImage=null;preview.update('<div class="pe-create-preview">Изображение не выбрано</div>');return;}validating=true;api('image',{house_id:C.houseId,path:value},function(image){if(request!==pending)return;validating=false;selectedImage=image;preview.update('<div class="pe-create-preview"><img src="'+E(image.url)+'" alt=""></div>');},function(){if(request===pending){validating=false;selectedImage=null;}});}
    var preview=new Ext.Panel({border:false,html:'<div class="pe-create-preview">'+(current?'<img src="'+E(current.url)+'" alt="">':'Выберите или загрузите PNG / JPG')+'</div>'});
    var w=new Ext.Window({title:'Изображение планировки',width:660,autoHeight:true,modal:true,items:[host,preview],buttons:[{text:'Использовать изображение',handler:function(){if(validating){fail('Дождитесь проверки изображения.');return;}if(!selectedImage){fail('Выберите изображение.');return;}callback(selectedImage);w.close();}},{text:'Отмена',handler:function(){w.close();}}],listeners:{destroy:function(){pending++;if(picker){if(picker.getInput&&picker.getInput().window)picker.getInput().window.destroy();if(picker.fileform)picker.fileform.destroy();if(picker.window)picker.window.destroy();picker.destroy();}}}});
    w.show();
    if(C.mixedImage&&window.mixedimage&&Ext.ComponentMgr.types['mixedimage-panel']){
        var config=Ext.apply({},C.mixedImage);Ext.apply(config,{xtype:'mixedimage-panel',renderTo:uid,tvId:uid,tvFieldId:'tv'+uid,value:current?current.path:'',removeFile:false,showPreview:false});
        // Separate field IDs and setValue keep the resource TV and dirty state untouched.
        picker=MODx.load(config);picker.setValue=function(value){this.getInput().setValue(value);accept(value);};
        picker.getInput().handleTrigger__manager=function(){chooseImage(null,function(image){picker.setValue(image.path);});};
    }else{
        new Ext.Container({renderTo:uid,layout:'hbox',items:[{xtype:'button',text:'Из файлов MODX',handler:function(){chooseImage(null,function(image){selectedImage=image;accept(image.path);});}},{xtype:'button',text:'С компьютера',handler:function(){upload(function(image){selectedImage=image;accept(image.path);});}}]});
    }
}
function creation(refresh){
    var createKey=requestKey(),creating=false;var title=field('Название'),section=field('Подъезд/Секция'),imageField=field('Изображение'),image=null,pins=new Ext.form.Checkbox({fieldLabel:'SVG-точки',checked:true});
    section.emptyText='Необязательно. Например: 2';imageField.setReadOnly(true);
    var setImage=function(i){image=i;imageField.setValue(i.path+' ('+i.width+' × '+i.height+')');};
    var previewBox=new Ext.Panel({border:false,html:'<div class="pe-create-preview">Готовое изображение PNG или JPG<br><small>Без обрезки и изменения размеров</small></div>'});
    var originalSetImage=setImage;setImage=function(i){originalSetImage(i);previewBox.update('<div class="pe-create-preview"><img src="'+E(i.url)+'" alt=""></div>');};
    var form=new Ext.form.FormPanel({bodyStyle:'padding:18px',labelAlign:'top',items:[title,section,previewBox,{xtype:'button',text:'Выбрать изображение',handler:function(){imagePicker(image,setImage);}},imageField,pins]});
    var win=new Ext.Window({title:'Добавить схему',modal:true,width:560,autoHeight:true,items:[form],buttons:[{text:'Создать и открыть',handler:function(){
        if(creating)return;if(!title.getValue().trim()||!image){fail('Укажите название и изображение.');return;}
        creating=true;win.getEl().mask('Создание…');api('save',{request_key:createKey,house_id:C.houseId,title:title.getValue(),section:section.getValue(),image_path:image.path,image_source:image.source,show_pins:pins.getValue()?1:0,svg_code:'<svg class="plan-svg__svg" viewBox="0 0 '+image.width+' '+image.height+'" xmlns="http://www.w3.org/2000/svg"></svg>'},function(s){win.close();refresh();edit(s,refresh);},function(){creating=false;win.getEl().unmask();});
    }},{text:'Отмена',handler:function(){win.close();}}]});win.show();
}
function assignments(s,refresh){
    api('floors',{house_id:C.houseId},function(floors){
        var snapshot={},root=new Ext.tree.TreeNode({text:'Все этажи',expanded:true}),groups=Object.create(null);
        floors.forEach(function(f){snapshot[f.id]=f.assignment;var group=f.section||'Без подъезда';if(!groups[group])groups[group]=root.appendChild(new Ext.tree.TreeNode({text:E(group),expanded:true,checked:false}));
            var suffix=f.assignment&&Number(f.assignment.scheme_id)>0&&Number(f.assignment.scheme_id)!==Number(s.id)?' — '+(knownSchemeTitles[f.assignment.scheme_id]||('схема #'+f.assignment.scheme_id)):'';
            groups[group].appendChild(new Ext.tree.TreeNode({text:E((f.number||f.title)+suffix),id:'pe-floor-'+f.id,leaf:true,checked:!!(f.assignment&&Number(f.assignment.scheme_id)===Number(s.id)),floor:f}));
        });
        var tree=new Ext.tree.TreePanel({root:root,rootVisible:false,autoScroll:true,border:false});
        tree.on('checkchange',function(n,checked){if(!n.isLeaf())n.eachChild(function(ch){ch.getUI().toggleCheck(checked);ch.attributes.checked=checked;});});
        var from=new Ext.form.NumberField({width:55,emptyText:'С'}),to=new Ext.form.NumberField({width:55,emptyText:'По'});
        var sectionStore=new Ext.data.ArrayStore({fields:['value'],data:[['Все подъезды']].concat(Object.keys(groups).map(function(x){return[x];}))});
        var groupSelect=new Ext.form.ComboBox({store:sectionStore,displayField:'value',valueField:'value',tpl:'<tpl for="."><div class="x-combo-list-item">{value:htmlEncode}</div></tpl>',mode:'local',triggerAction:'all',editable:false,value:'Все подъезды',width:160});
        var win=new Ext.Window({title:'Назначить этажам: '+E(s.title),modal:true,width:580,height:560,layout:'fit',items:[tree],tbar:[groupSelect,from,to,{text:'Выбрать диапазон',handler:function(){var a=from.getValue(),b=to.getValue();if(a===''||b===''||a>b){fail('Укажите корректный диапазон.');return;}root.cascade(function(n){var f=n.attributes.floor;if(f && String(f.number).trim()!=='' && Number.isFinite(Number(f.number)) && Number(f.number)>=a && Number(f.number)<=b && (groupSelect.getValue()==='Все подъезды'||(f.section||'Без подъезда')===groupSelect.getValue())){n.getUI().toggleCheck(true);n.attributes.checked=true;}});}}],buttons:[{text:'Сохранить назначения',handler:function(){
            var selected=tree.getChecked().filter(function(n){return !!n.attributes.floor;}).map(function(n){return n.attributes.floor.id;});
            var conflicts=selected.filter(function(fid){return snapshot[fid]&&Number(snapshot[fid].scheme_id)>0&&Number(snapshot[fid].scheme_id)!==Number(s.id);});
            if(conflicts.length&&!window.confirm('Заменить текущие схемы этажей: '+conflicts.map(function(id){var f=floors.find(function(f){return f.id===id;});return(f.section?f.section+' / ':'')+(f.number||f.title)+' (#'+id+')';}).join(', ')+'?'))return;
            api('assign',{id:s.id,selected:Ext.encode(selected),snapshot:Ext.encode(snapshot),confirm_replace:conflicts.length?1:0},function(){win.close();refresh();});
        }},{text:'Отмена',handler:function(){win.close();}}]});win.show();
    });
}
function preview(s){
    var w=new Ext.Window({title:'Просмотр: '+E(s.title),width:Math.min(window.innerWidth-30,960),height:Math.min(window.innerHeight-40,700),modal:true,maximizable:true,layout:'fit',items:[{xtype:'panel',autoScroll:true,html:'<div class="pe-preview"><img alt="'+E(s.title)+'" src="'+E(s.image.url)+'">'+s.svg_code+'</div>'}]});w.show();
}
function changeFloor(refresh){
    api('floor-info',{floor_id:C.floorId},function(info){api('list',{house_id:C.houseId},function(items){
        var store=new Ext.data.ArrayStore({fields:['id','title'],data:[[0,'Схема не выбрана']].concat(items.map(function(s){return[s.id,s.title];}))});
        var select=new Ext.form.ComboBox({store:store,valueField:'id',displayField:'title',tpl:'<tpl for="."><div class="x-combo-list-item">{title:htmlEncode}</div></tpl>',mode:'local',triggerAction:'all',editable:false,value:info.scheme?info.scheme.id:0,anchor:'100%'});
        var w=new Ext.Window({title:'Планировка этажа #'+C.floorId,width:440,autoHeight:true,modal:true,items:[{xtype:'form',bodyStyle:'padding:16px',items:[select]}],buttons:[{text:'Применить',handler:function(){
            if(!window.confirm('Изменить назначение только текущему этажу #'+C.floorId+'?'))return;
            api('set-floor',{floor_id:C.floorId,scheme_id:select.getValue()||0,snapshot:Ext.encode(info.assignment)},function(){w.close();refresh();});
        }},{text:'Отмена',handler:function(){w.close();}}]});w.show();
    });});
}
function acePanel(initial){
    var editor=null,id=Ext.id(),panel=new Ext.Panel({border:false,layout:'fit',html:'<div id="'+id+'" style="width:100%;height:100%;min-height:300px"></div>',listeners:{afterrender:function(){
        editor=ace.edit(id);editor.setOptions({fontSize:14,showPrintMargin:false,useWorker:false,tabSize:2,wrap:true});editor.session.setMode('ace/mode/html');editor.setTheme('ace/theme/textmate');editor.setValue(initial,-1);editor.resize();
    },destroy:function(){if(editor)editor.destroy();}}});
    panel.getValue=function(){return editor?editor.getValue():initial;};panel.selectText=function(){if(editor)editor.selectAll();};panel.focus=function(){if(editor)editor.focus();};panel.resizeAce=function(){if(editor)editor.resize();};return panel;
}
function toolIcon(name){var paths={undo:'M4 9h10a6 6 0 0 1 0 12 M4 9l5-5 M4 9l5 5',redo:'M20 9H10a6 6 0 0 0 0 12 M20 9l-5-5 M20 9l-5 5',angle:'M5 3v17h16 M5 14h6v6',select:'M4 3l15 9-7 1-3 7z',pan:'M7 12V6a2 2 0 0 1 4 0v5 M11 10V4a2 2 0 0 1 4 0v8 M15 10V6a2 2 0 0 1 4 0v9c0 5-3 7-7 7-3 0-5-2-7-5l-2-4a2 2 0 0 1 3-2l3 3',draw:'M4 5l10-2 6 8-7 10-10-5z M16 18h7 M19.5 14.5v7',pin:'M12 21s7-8 7-13a7 7 0 0 0-14 0c0 5 7 13 7 13z M12 5v6 M9 8h6',minus:'M5 12h14',plus:'M5 12h14 M12 5v14',fit:'M3 9V3h6 M15 3h6v6 M21 15v6h-6 M9 21H3v-6',trash:'M3 6h18 M9 6V3h6v3 M6 6l1 15h10l1-15',copy:'M9 9h12v12H9z M15 9V3H3v12h6'};return 'data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path d="'+paths[name]+'" fill="none" stroke="#425c68" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round"/></svg>');}
function edit(s,refresh){
    var dirty=false,saving=false,canvas=null,syncing=false;
    var title=field('Название',s.title),section=field('Подъезд/Секция',s.section),number=field('Номер',''),keyField=field('data-i','');
    section.emptyText='Необязательно';
    var pins=new Ext.form.Checkbox({fieldLabel:'Показывать SVG-точки',checked:!!Number(s.show_pins)}),statusText=new Ext.Toolbar.TextItem(''),saveText='Все изменения сохранены';
    var panelId=Ext.id(),host=new Ext.Panel({region:'center',layout:'fit',border:false,html:'<div id="'+panelId+'" style="height:100%"></div>',listeners:{afterrender:function(){loadCanvas();}}});
    var properties=new Ext.form.FormPanel({region:'east',title:'Выбранный контур',width:260,minSize:220,split:true,autoScroll:true,labelAlign:'top',bodyStyle:'padding:18px',cls:'pe-properties',items:[number,keyField,{xtype:'container',layout:'column',height:62,width:215,defaults:{width:48,style:'margin-right:8px'},items:[{xtype:'button',icon:toolIcon('pin'),tooltip:'Поставить SVG-точку в центре выбранной области',handler:function(){canvas.movePinToCenter();}},{xtype:'button',icon:toolIcon('copy'),tooltip:'Копировать контур с тем же ключом',handler:function(){if(canvas.selected<0)return;canvas.remember();var a=canvas.areas[canvas.selected];canvas.areas.push({key:a.key,d:a.d,points:a.points.map(function(p){return p.slice();})});canvas.selected=canvas.areas.length-1;canvas.touch();canvas.render();selected(canvas.areas[canvas.selected]);}},{xtype:'button',icon:toolIcon('trash'),tooltip:'Удалить выбранную область',handler:function(){canvas.deleteArea();}}]}]});
    function updateStatus(){if(!canvas)return;statusText.setText('<span class="'+(dirty?'pe-unsaved':'pe-saved')+'">'+E(dirty?'Есть изменения':saveText)+'</span>');if(win)win.setTitle('PlanPoint · '+E(title.getValue())+(dirty?' *':''));if(selectButton){selectButton.toggle(!canvas.drawMode&&!canvas.panMode,true);drawButton.toggle(canvas.drawMode,true);handButton.toggle(canvas.panMode,true);if(angleButton)angleButton.toggle(canvas.ortho,true);}}
    function changed(){dirty=true;if(canvas&&canvas.scheme.image){s.image=canvas.scheme.image;if(imagePreview&&imagePreview.rendered)imagePreview.update('<div class="pp-image-preview"><img src="'+E(s.image.url)+'" alt=""></div>');}updateStatus();}
    function selected(a){syncing=true;keyField.setValue(a?a.key:'');var prefix='x'+section.getValue().trim()+'-';number.setValue(a&&a.key.indexOf(prefix)===0?a.key.slice(prefix.length):'');[number,keyField].forEach(function(f){f.setDisabled(!a);});syncing=false;updateStatus();}
    function loadCanvas(){if(canvas)canvas.destroy();canvas=new window.PlanPointCanvas(document.getElementById(panelId),s,changed,selected);canvas.isActive=function(){return Ext.WindowMgr.getActive()===win&&tabs.getActiveTab()===editorPanel;};selected(null);updateStatus();}
    function rename(fromKey){if(syncing||!canvas||canvas.selected<0)return;try{canvas.rename(fromKey?keyField.getValue().trim():G.key(section.getValue().trim(),number.getValue().trim()));selected(canvas.areas[canvas.selected]);}catch(e){fail(e.message);}}
    number.on('change',function(){rename(false);});keyField.on('change',function(){rename(true);});
    title.on('change',changed);section.on('change',function(){s.section=section.getValue().trim();changed();});pins.on('check',function(_,value){s.show_pins=value?1:0;if(canvas)canvas.render();changed();});
    function codeWindow(initial,svgOnly){
        var text=acePanel(initial),w=new Ext.Window({title:svgOnly?'Импорт SVG — замена контуров и точек':'Код схемы: изображение, контуры, SVG-точки',modal:true,width:850,height:550,layout:'fit',items:[text],buttons:[{text:'Скачать',handler:function(){var blob=new Blob([text.getValue()],{type:svgOnly?'image/svg+xml;charset=utf-8':'text/html;charset=utf-8'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=svgOnly?'planpoint.svg':'planpoint.html';document.body.appendChild(a);a.click();a.remove();setTimeout(function(){URL.revokeObjectURL(url);},1000);}},{text:'Копировать',handler:function(){text.focus();text.selectText();if(navigator.clipboard)navigator.clipboard.writeText(text.getValue()).catch(function(){document.execCommand('copy');});else document.execCommand('copy');}},{text:'Применить',handler:function(){
            var code=text.getValue(),imagePath=s.image.path;
            try{
                if(!svgOnly){
                    var d=new DOMParser().parseFromString(code,'text/html'),children=Array.from(d.body.children);
                    if(d.head.children.length || Array.from(d.body.childNodes).some(function(n){return n.nodeType===3&&n.textContent.trim();}))throw Error('Лишние узлы полного кода не поддерживаются.');
                    if(children.length!==1||children[0].tagName!=='DIV'||children[0].getAttribute('class')!=='plan-svg')throw Error('Ожидается одна обёртка div.plan-svg.');
                    var wrapper=children[0],parts=Array.from(wrapper.children);
                    if(Array.from(wrapper.childNodes).some(function(n){return n.nodeType===3&&n.textContent.trim();}))throw Error('Лишний текст в коде схемы.');
                    if(parts.length!==2||parts[0].tagName!=='PICTURE'||parts[1].tagName.toLowerCase()!=='svg')throw Error('Внутри нужны только picture и svg.');
                    var picture=parts[0],img=picture.children[0];if(picture.children.length!==1||!img||img.tagName!=='IMG')throw Error('Нужен один img внутри picture.');
                    [wrapper,picture,img].forEach(function(n){Array.from(n.attributes).forEach(function(a){var allowed=n===wrapper?['class']:n===picture?[]:['src','alt','class'];if(allowed.indexOf(a.name)<0)throw Error('Неподдержанный атрибут '+a.name);});});
                    if(img.getAttribute('src')!==s.image.url)throw Error('В первой альфе меняйте изображение кнопкой выбора: ссылка полного кода должна совпадать с текущей.');
                    code=new XMLSerializer().serializeToString(parts[1]);
                }
            }catch(e){fail(e.message);return;}
            api('validate',{house_id:C.houseId,code:code},function(doc){
                var v=doc.viewbox.split(' ').map(Number),mismatch=v[0]!==0||v[1]!==0||v[2]!==s.image.width||v[3]!==s.image.height;
                if(!window.confirm('Заменить все контуры и точки? Изображение и назначения сохранятся.'+(mismatch?' viewBox '+doc.viewbox+' отличается от изображения '+s.image.width+' × '+s.image.height+'.':'')))return;
                s.svg_code=doc.svg;loadCanvas();changed();w.close();
            });
        }},{text:'Отмена',handler:function(){w.close();}}]});w.on('resize',function(){text.resizeAce();});w.show();
    }
    function fullCode(){var div=document.createElement('div');div.className='plan-svg';var picture=document.createElement('picture'),img=document.createElement('img');img.setAttribute('src',s.image.url);img.setAttribute('alt',title.getValue());img.className='plan-svg__img';picture.appendChild(img);div.appendChild(picture);var svg=new DOMParser().parseFromString(canvas.serialize(),'image/svg+xml').documentElement;div.appendChild(document.importNode(svg,true));return div.outerHTML;}
    function replaceImage(image){
        canvas.remember();s.image=image;canvas.scheme.image=image;canvas.view=[0,0,image.width,image.height];canvas.svg.setAttribute('viewBox',canvas.view.join(' '));canvas.svg.style.aspectRatio=image.width+'/'+image.height;canvas.touch();canvas.fit();
    }
    function saveScheme(){
        if(saving||!canvas)return;var discardDraft=false;
        if(canvas.draft&&canvas.draft.length){if(!window.confirm('Отбросить только незамкнутый контур и сохранить остальные?'))return;discardDraft=true;}
        var used=!!(s.assignments&&s.assignments.length);
        function submit(){
            if(discardDraft){canvas.remember();canvas.draft=null;canvas.render();}
            saving=true;saveButton.setDisabled(true);win.getEl().mask('Сохранение…');
            api('save',{id:s.id,house_id:C.houseId,title:title.getValue(),section:section.getValue(),image_path:s.image.path,image_source:s.image.source,svg_code:canvas.serialize(),show_pins:pins.getValue()?1:0,revision:s.revision,confirm_used:used?1:0},function(updated){
                var selectedIndex=canvas.selected,zoom=canvas.scale,draw=canvas.drawMode;s=updated;dirty=false;saving=false;saveText='Сохранено в '+new Date().toLocaleTimeString();win.getEl().unmask();saveButton.setDisabled(false);loadCanvas();canvas.zoom(zoom);if(selectedIndex>=0&&selectedIndex<canvas.areas.length)canvas.selectArea(selectedIndex);if(draw)canvas.start();updateStatus();refresh();
            },function(){saving=false;saveButton.setDisabled(false);win.getEl().unmask();updateStatus();});
        }
        if(used)Ext.Msg.confirm('Сохранить используемую схему?',E(G.floorSummary(s.floor_labels))+'. Изменения увидят все эти этажи.',function(answer){if(answer==='yes')submit();});else submit();
    }
    var saveButton=new Ext.Button({text:'Сохранить',cls:'primary-button',handler:saveScheme});
    function tool(mode){if(canvas.tool(mode))updateStatus();}
    function toolState(name){return {toggle:function(pressed){if(!tools||!tools.rendered)return;var node=tools.getEl().dom.querySelector('button[data-tool="'+name+'"]');if(node){node.classList.toggle('pp-active',!!pressed);node.setAttribute('aria-pressed',pressed?'true':'false');}}};}
    var selectButton=toolState('select'),drawButton=toolState('draw'),handButton=toolState('pan'),angleButton=toolState('angle');
    var toolList=[['select','select','Выделить контур'],['pan','pan','Перемещать обзор · Пробел + мышь'],['draw','draw','Непрерывное рисование · N'],['angle','angle','Прямые углы · Shift временно'],['pin','pin','SVG-точка в центре'],['undo','undo','Отменить · Ctrl+Z'],['redo','redo','Повторить · Ctrl+Shift+Z'],['minus','minus','Уменьшить'],['plus','plus','Увеличить'],['fit','fit','Вписать схему']];
    var tools=new Ext.Panel({region:'west',width:62,border:false,cls:'pp-tools',autoScroll:true,html:toolList.map(function(a){return '<button type="button" class="pp-tool" data-tool="'+a[0]+'" title="'+E(a[2])+'" aria-label="'+E(a[2])+'"><img alt="" src="'+toolIcon(a[1])+'"></button>';}).join(''),listeners:{afterrender:function(p){p.getEl().on('click',function(e){var b=e.getTarget('button[data-tool]');if(!b||!canvas)return;var name=b.getAttribute('data-tool');if(name==='select'||name==='pan'||name==='draw')tool(name);else if(name==='angle'){canvas.ortho=!canvas.ortho;canvas.preview();}else if(name==='pin')canvas.movePinToCenter();else if(name==='undo')canvas.undo();else if(name==='redo')canvas.redo();else if(name==='fit')canvas.fit();else canvas.zoom(name==='plus'?1.2:1/1.2);updateStatus();});}}});
    var editorPanel=new Ext.Panel({title:'Редактор',layout:'border',border:false,items:[tools,host,properties]});
    var imagePreview=new Ext.Panel({border:false,html:'<div class="pp-image-preview"><img src="'+E(s.image.url)+'" alt=""></div>'});
    var settingsPanel=new Ext.form.FormPanel({title:'Изображение',autoScroll:true,labelAlign:'top',bodyStyle:'padding:20px',items:[title,section,pins,imagePreview,{xtype:'button',text:'Заменить изображение',handler:function(){imagePicker(s.image,function(image){replaceImage(image);imagePreview.update('<div class="pp-image-preview"><img src="'+E(s.image.url)+'" alt=""></div>');});}}]});
    var codePanel=new Ext.Panel({title:'Код SVG',bodyStyle:'padding:20px',items:[{xtype:'button',text:'Редактировать SVG · Ace',handler:function(){codeWindow(canvas.serialize(),true);}},{xtype:'button',text:'Полный код схемы · Ace',handler:function(){codeWindow(fullCode(),false);}},{xtype:'button',text:'Импортировать SVG-файл',handler:function(){var i=document.createElement('input');i.type='file';i.accept='.svg';i.onchange=function(){if(i.files[0]){if(i.files[0].size>1048576){fail('SVG больше 1 МБ.');return;}i.files[0].text().then(function(t){codeWindow(t,true);});}};i.click();}}]});
    var tabs=new Ext.TabPanel({activeTab:0,deferredRender:false,height:Math.max(390,Math.min(window.innerHeight-240,720)),items:[editorPanel,settingsPanel,codePanel]});
    var help=new Ext.Panel({border:false,cls:'pp-help',html:'<h3>Как работать со схемой</h3><p><b>Обводка:</b> выберите многоугольник слева и ставьте точки по границе помещения. Линия до курсора показывает следующую сторону. Для замыкания щёлкните первую точку (минимум три вершины). Следующий щелчок начнёт новую область с очередным номером.</p><p><b>Прямые углы:</b> удерживайте Shift для горизонтальной или вертикальной стороны. Кнопка угла слева включает этот режим постоянно. При замыкании недостающий прямоугольный угол добавляется автоматически.</p><p><b>Исправления:</b> Ctrl+Z отменяет последнюю точку или правку; Ctrl+Shift+Z / Ctrl+Y возвращает её. Esc отменяет текущий незавершённый контур; повторный Esc включает выделение. Готовый контур выберите стрелкой: вершины можно двигать, двойной щелчок возле ребра добавляет вершину, правая кнопка по вершине удаляет её.</p><p><b>Номера:</b> справа меняйте номер либо точный data-i. Секция задаётся для всей схемы на вкладке «Изображение». Повторные ключи допускаются; SVG-точка у них общая.</p><p><b>Обзор и сохранение:</b> Пробел + мышь, средняя кнопка или ладонь — перемещение; + / − — масштаб; рамка — вписать. Ctrl+S сохраняет. История отмены действует до сохранения/импорта; текстовые поля и Ace используют свою отмену.</p>'});
    var content=new Ext.Panel({border:false,autoScroll:true,layout:'auto',items:[tabs,help]});
    var win=new Ext.Window({title:'PlanPoint · '+E(s.title),modal:true,onEsc:Ext.emptyFn,cls:'pe-editor-window',width:Math.min(window.innerWidth-40,1440),height:Math.min(window.innerHeight-50,900),maximizable:true,resizable:true,layout:'fit',items:[content],tbar:[{xtype:'tbtext',text:'Редактирование планировки'},statusText,'->',saveButton,{text:'Закрыть',handler:function(){win.close();}}],listeners:{resize:function(w,width,height){if(tabs.rendered){tabs.setHeight(Math.max(390,Math.min(height-185,850)));content.doLayout();}},beforeclose:function(){if(saving)return false;return !dirty||window.confirm('Есть несохранённые изменения. Закрыть редактор?');},destroy:function(){document.removeEventListener('keydown',saveKeys);window.removeEventListener('beforeunload',beforeUnload);if(canvas)canvas.destroy();}}});
    function saveKeys(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'&&Ext.WindowMgr.getActive()===win){e.preventDefault();if(document.activeElement)document.activeElement.blur();saveScheme();}}
    function beforeUnload(e){if(dirty){e.preventDefault();e.returnValue='';}}
    document.addEventListener('keydown',saveKeys);window.addEventListener('beforeunload',beforeUnload);win.show();updateStatus();
}
function copySchemes(schemes,refresh){
    if(!schemes.length){fail('Выберите хотя бы одну схему.');return;}
    api('houses',{},function(houses){
        var store=new Ext.data.ArrayStore({fields:['id','caption'],data:houses.map(function(h){return[h.id,h.title+' (#'+h.id+')'];})});
        var choice=new Ext.form.ComboBox({fieldLabel:'Дом назначения',store:store,valueField:'id',displayField:'caption',tpl:'<tpl for="."><div class="x-combo-list-item">{caption:htmlEncode}</div></tpl>',mode:'local',triggerAction:'all',editable:false,value:C.houseId,anchor:'100%'});
        var copyKey=requestKey(),copying=false;var w=new Ext.Window({title:'Копировать '+schemes.length+' схем',modal:true,width:480,autoHeight:true,items:[{xtype:'form',bodyStyle:'padding:18px',labelAlign:'top',items:[choice,{xtype:'displayfield',value:'Создадутся копии без назначений этажам. Изображения останутся общими, номера и секция сохранятся.'}]}],buttons:[{text:'Копировать',handler:function(){if(copying)return;if(!choice.getValue()){fail('Выберите дом.');return;}copying=true;w.getEl().mask('Копирование…');api('copy',{request_key:copyKey,ids:Ext.encode(schemes.map(function(s){return s.id;})),target:choice.getValue()},function(){w.close();refresh();},function(){copying=false;w.getEl().unmask();});}},{text:'Отмена',handler:function(){w.close();}}]});w.show();
    });
}
function removeScheme(s,refresh){
    var summary=G.floorSummary(s.floor_labels);
    Ext.Msg.confirm('Удалить схему «'+E(s.title)+'»?',E(summary)+'. Удалятся схема и её назначения. Продолжить?',function(answer){
        if(answer!=='yes')return;
        Ext.Msg.show({title:'Изображение схемы',msg:'Удалить также изображение? Общий файл или файл вне папки модуля будет сохранён. Внешние ссылки в коде сайта не проверяются.',buttons:Ext.Msg.YESNOCANCEL,fn:function(answer){if(answer==='cancel')return;api('remove',{id:s.id,revision:s.revision,confirm:1,delete_file:answer==='yes'?1:0},function(data){refresh();if(data.file_preserved)MODx.msg.status({title:'PlanPoint',message:E(data.message)});});}});
    });
}
function actionIcon(name){
    var paths={eye:'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',pencil:'M4 16l12-12 4 4-12 12H4v-4z M14 6l4 4',building:'M5 21V3h14v18 M2 21h20 M8 7h2 M14 7h2 M8 11h2 M14 11h2 M10 21v-6h4v6',copy:'M9 9h12v12H9z M15 9V3H3v12h6',trash:'M3 6h18 M9 6V3h6v3 M6 6l1 15h10l1-15 M10 10v7 M14 10v7'};
    return 'data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="'+paths[name]+'" fill="none" stroke="'+(name==='trash'?'#b34d42':'#53636d')+'" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>');
}
function makePanel(){
    var store=new Ext.data.JsonStore({fields:['id','title','image','assignments','floor_labels','revision','section','show_pins','svg_code'],data:[]});
    var sm=new Ext.grid.CheckboxSelectionModel(),search=new Ext.form.TextField({emptyText:'Найти схему по названию…',width:220}),count=new Ext.Toolbar.TextItem('');
    var copyButton=new Ext.Button({text:'Копировать выбранные',disabled:true,handler:function(){copySchemes(sm.getSelections().map(function(r){return r.data;}),refresh);}});
    var grid=new Ext.grid.GridPanel({store:store,sm:sm,cls:'pe-scheme-grid',autoHeight:true,stripeRows:true,viewConfig:{forceFit:true,emptyText:'<div class="pe-empty"><b>Добавьте первую схему</b><br>Выберите готовое изображение, обведите помещения и назначьте схему этажам.</div>',deferEmptyText:false},columns:[sm,{header:'Схема',dataIndex:'image',width:112,sortable:false,fixed:true,renderer:function(image){return image?'<img class="pe-thumb" src="'+E(image.url)+'" alt="">':'';}},{header:'Название',dataIndex:'title',width:235,sortable:true,renderer:function(v,meta,r){return '<div class="pe-row-title">'+E(v)+'</div><div class="pe-row-meta">'+r.data.image.width+' × '+r.data.image.height+' · '+(r.data.assignments.length?'Назначена этажам':'Без назначений')+'</div>';}},{header:'Этажи',dataIndex:'floor_labels',width:330,renderer:function(f){return '<div class="pe-floor-summary">'+E(G.floorSummary(f))+'</div>';}},{header:'Действия',width:450,fixed:true,sortable:false,renderer:function(){return '<div class="pp-actions">'+[['preview','Просмотр'],['edit','Изменить'],['assign','Этажи'],['copy','Копия'],['delete','Удалить']].map(function(a){return '<button type="button" data-act="'+a[0]+'" class="pp-row-button '+(a[0]==='delete'?'pp-remove':'')+'">'+a[1]+'</button>';}).join('')+'</div>';}}
    ],tbar:[{text:'Добавить схему',cls:'primary-button',handler:function(){creation(refresh);}},copyButton,'->',search,{text:'Обновить',handler:refresh}],bbar:[count]});
    function dispatch(action,record){var s=record.data;if(action==='preview')api('get',{id:s.id},preview);if(action==='edit')api('get',{id:s.id},function(data){edit(data,refresh);});if(action==='assign')assignments(s,refresh);if(action==='copy')copySchemes([s],refresh);if(action==='delete')removeScheme(s,refresh);}
    grid.on('cellclick',function(g,row,col,e){var button=e.getTarget('button[data-act]');if(button){e.stopEvent();dispatch(button.getAttribute('data-act'),store.getAt(row));}});
    grid.on('rowcontextmenu',function(g,row,e){e.stopEvent();sm.selectRow(row);var record=store.getAt(row);var menu=new Ext.menu.Menu({items:[{text:'Просмотреть',handler:function(){dispatch('preview',record);}},{text:'Редактировать',handler:function(){dispatch('edit',record);}},{text:'Назначить этажам',handler:function(){dispatch('assign',record);}},{text:'Копировать',handler:function(){dispatch('copy',record);}},'-',{text:'Удалить',handler:function(){dispatch('delete',record);}}]});menu.showAt(e.getXY());});
    sm.on('selectionchange',function(){copyButton.setDisabled(!sm.getCount());});
    grid.on('rowdblclick',function(g,index){api('get',{id:store.getAt(index).data.id},function(s){edit(s,refresh);});});
    search.on('render',function(){search.getEl().on('keyup',applyFilter);});search.on('change',applyFilter);search.on('specialkey',function(f,e){if(e.getKey()===e.ENTER)applyFilter();});
    function applyFilter(){var q=search.getValue().trim().toLowerCase();store.clearFilter();if(q)store.filterBy(function(r){return String(r.data.title).toLowerCase().indexOf(q)>=0;});count.setText('Схем: '+store.getCount());}
    var info=new Ext.Panel({border:false,cls:'pe-house-info',tbar:C.floorId?[{text:'Изменить назначение этого этажа',handler:function(){changeFloor(refresh);}}]:undefined,html:'<div class="pe-note"><b>PlanPoint</b> · Планировки дома</div>'});
    function refresh(){grid.getEl()&&grid.getEl().mask('Загрузка схем…');api('list',{house_id:C.houseId},function(items){items.forEach(function(s){knownSchemeTitles[s.id]=s.title;});store.loadData(items);applyFilter();grid.getEl()&&grid.getEl().unmask();
        if(C.floorId){var found=items.find(function(s){return s.assignments.some(function(a){return Number(a.floor_id)===C.floorId;});});info.update('<div class="pe-floor-card">'+(found?'<img class="pe-thumb" src="'+E(found.image.url)+'" alt="">':'')+'<div><b>Планировка этого этажа</b><br>'+(found?E(found.title):'Схема не выбрана')+'<p>Кнопка выше изменит назначение только текущему этажу.</p></div></div>');}
    },function(){grid.getEl()&&grid.getEl().unmask();});}
    return new Ext.Panel({id:'pp-content',title:'Планировки',layout:'auto',autoHeight:true,items:[info,grid],listeners:{afterrender:refresh}});
}
Ext.onReady(function(){
    var attempts=0;function attach(){var tabs=Ext.getCmp('modx-resource-tabs');if(!tabs){if(++attempts<30)setTimeout(attach,200);return;}if(Ext.getCmp('pp-resource-tab'))return;var original=tabs.getActiveTab();
    var holder=new Ext.Panel({id:'pp-resource-tab',title:'Планировки',layout:'auto',border:false,autoHeight:true,listeners:{activate:function(p){if(!p.ppLoaded){p.ppLoaded=true;p.add(makePanel());p.doLayout();}}}});
    tabs.add(holder);
    if(original&&tabs.getActiveTab()!==original)tabs.setActiveTab(original);
}attach();
});
}());
