'use strict';
// Uses the genuine Ext JS bundled with a local MODX 2.8 / 3.2 source checkout.
// MODX_SOURCE=/path/to/revolution node tests/browser.test.js
const {chromium}=require('playwright'),http=require('http'),fs=require('fs'),path=require('path'),assert=require('assert/strict');
const source=process.env.MODX_SOURCE;if(!source)throw Error('Set MODX_SOURCE to a MODX source directory');
const project=path.resolve(__dirname,'..');
const output=process.env.PLAYWRIGHT_OUTPUT_DIR||path.join(project,'.artifacts');fs.mkdirSync(output,{recursive:true});
const initialSvg='<svg class="plan-svg__svg" viewBox="0 0 800 500" xmlns="http://www.w3.org/2000/svg"></svg>';
let schemes=[],calls=[];
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname==='/api'){
        let body='';req.on('data',c=>body+=c);req.on('end',()=>{const p=new URLSearchParams(body);calls.push(Object.fromEntries(p));let data;
            switch(p.get('op')){
                case 'list':data=schemes.map(({svg_code,...summary})=>summary);break;
                case 'get':data=schemes[0];break;
                case 'save':{let s={id:1,revision:schemes.length?schemes[0].revision+1:1,title:p.get('title'),section:p.get('section')||'',svg_code:p.get('svg_code'),show_pins:Number(p.get('show_pins')),image:{source:1,path:'plans/test.png',url:'/image.png',width:800,height:500},assignments:[]};schemes=[s];data=s;break;}
                case 'image':data={source:1,path:p.get('path'),url:'/image.png',width:1200,height:700};break;
                case 'validate':data={svg:p.get('code'),viewbox:'0 0 800 500'};break;
                case 'houses':data=[{id:1,title:'Дом 1'},{id:2,title:'Дом 2'}];break;
                case 'floors':data=[{id:10,title:'Этаж 10',number:'10',section:'Подъезд 1',assignment:null},{id:11,title:'Этаж 11',number:'11',section:'Подъезд 1',assignment:null}];break;
                case 'assign':data={assigned:1};break;
                default:data={};
            }
            res.setHeader('Content-Type','application/json');res.end(JSON.stringify({success:true,object:{data}}));});return;
    }
    if(url.pathname==='/mixed.js'){res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(process.env.MIXEDIMAGE_JS||path.join(__dirname,'../../runtime/mixedimage.js')));return;}
    if(url.pathname==='/image.png'){res.setHeader('Content-Type','image/png');res.end(fs.readFileSync(path.join(__dirname,'fixtures/plan.png')));return;}
    if(url.pathname==='/canvas'){
        res.setHeader('Content-Type','text/html');res.end(`<html><meta charset="utf-8"><link rel="stylesheet" href="/pe/css/manager.css"><div id="canvas" style="width:900px;height:600px"></div><script src="/pe/js/geometry.js"></script><script src="/pe/js/editor.js"></script><script>window.changes=0;window.editor=new PlanPointCanvas(document.getElementById('canvas'),{section:'',show_pins:1,image:{url:'/image.png'},svg_code:${JSON.stringify(initialSvg)}},()=>changes++);</script></html>`);return;
    }
    if(url.pathname==='/'){
        res.setHeader('Content-Type','text/html');res.end(`<html><meta charset="utf-8"><link rel="stylesheet" href="/ext/resources/css/ext-all.css"><link rel="stylesheet" href="/modx/templates/default/css/index.css"><link rel="stylesheet" href="/pe/css/manager.css"><script src="/ext/adapter/ext/ext-base.js"></script><script src="/ext/ext-all.js"></script><script>window._=function(k){return k;};window.resourceChanges=0;window.browserCreations=0;window.MODx={siteId:'test-token',config:{assets_url:'/assets/',connectors_url:'/connectors/'},fireResourceFormChange:function(){resourceChanges++;},load:function(c){if(c.xtype==='modx-browser'){browserCreations++;window.testBrowser={show:function(){},hide:function(){},select:c.listeners.select};return testBrowser;}return Ext.ComponentMgr.create(c);}};window.PlanPointConfig={houseId:1,floorId:0,sourceId:1,connector:'/api',mixedImage:{source:1,ms_id:1,tv_id:1,res_id:1,triggerlist:'clear,manager,pc,url',openPath:'img',showPreview:false,removeFile:false}};Ext.onReady(function(){new Ext.TabPanel({id:'modx-resource-tabs',renderTo:document.body,width:1100,height:700,activeTab:0,items:[{title:'Ресурс'}]});});</script><script src="/modx/assets/modext/widgets/core/modx.window.js"></script><script src="/mixed.js"></script><script>Ext.reg('modx-combo-browser',Ext.form.TextField);mixedimage.panel.prototype.loadFileForm=function(){this.fileform={destroy:function(){}};};</script><script src="/pe/vendor/ace/ace.js"></script><script src="/pe/vendor/ace/mode-html.js"></script><script src="/pe/vendor/ace/theme-textmate.js"></script><script src="/pe/js/geometry.js"></script><script src="/pe/js/editor.js"></script><script src="/pe/js/manager.js"></script></html>`);return;
    }
    let file;if(url.pathname.startsWith('/ext/'))file=path.join(source,'manager/assets/ext3',url.pathname.slice(5));
    else if(url.pathname.startsWith('/modx/'))file=path.join(source,'manager',url.pathname.slice(6));
    else if(url.pathname.startsWith('/pe/'))file=path.join(project,'assets/components/planpoint',url.pathname.slice(4));
    if(!file||!fs.existsSync(file)){res.statusCode=404;res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(file));
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const base='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_EXECUTABLE||undefined,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.setDefaultTimeout(10000);page.on('pageerror',e=>{errors.push(e.message);console.error('PAGE',e.message);});page.on('dialog',d=>d.accept());
try{
    await page.goto(base+'/canvas');
    await page.evaluate(()=>editor.start());
    await page.evaluate(()=>{editor.draft=[[100,100]];});
    await page.evaluate(()=>{var host=document.createElement('div');document.body.appendChild(host);var disabled=new PlanPointCanvas(host,{section:'',show_pins:'0',image:{url:'/image.png'},svg_code:editor.original});disabled.draft=[[0,0],[10,0],[0,10]];disabled.close(false);if(disabled.pins.length)throw Error('Disabled string flag created a pin');disabled.destroy();host.remove();});
    const originalDraft=await page.evaluate(()=>JSON.stringify(editor.draft));
    await page.keyboard.press('Escape');assert.equal(await page.evaluate(()=>editor.draft.length),0);
    await page.keyboard.press('Control+z');assert.equal(await page.evaluate(()=>JSON.stringify(editor.draft)),originalDraft);
    await page.keyboard.press('Control+Shift+z');assert.equal(await page.evaluate(()=>editor.draft.length),0);
    await page.evaluate(()=>{editor.draft=[];editor.history=[];editor.future=[];});
    // Coordinates on actual SVG are mapped using its screen matrix.
    const click=async(x,y)=>{const p=await page.evaluate(([x,y])=>{const p=editor.svg.createSVGPoint();p.x=x;p.y=y;const q=p.matrixTransform(editor.svg.getScreenCTM());return{x:q.x,y:q.y};},[x,y]);await page.mouse.click(p.x,p.y);};
    await click(100,100);await page.keyboard.down('Shift');await click(270,130);await page.keyboard.up('Shift');assert.equal(await page.evaluate(()=>editor.draft[1][1]),100);await page.keyboard.press('Control+z');assert.equal(await page.evaluate(()=>editor.draft.length),1);await page.keyboard.press('Control+Shift+z');assert.equal(await page.evaluate(()=>editor.draft.length),2);await page.keyboard.press('Control+z');await page.mouse.move(450,240);assert(await page.locator('.pe-draft-preview').getAttribute('points'));await click(300,100);await click(300,300);await click(100,300);await page.keyboard.press('Enter');assert.equal(await page.evaluate(()=>editor.areas.length),0);await click(100,100);
    assert.equal(await page.evaluate(()=>editor.areas.length),1);assert.equal(await page.evaluate(()=>editor.selected),0);assert.equal(await page.evaluate(()=>editor.pins.length),1);
    await page.evaluate(()=>editor.rename('x-06'));assert.equal(await page.evaluate(()=>editor.pins[0].key),'x-06');
    assert.equal(await page.evaluate(()=>editor.drawMode),true);assert.equal(await page.evaluate(()=>editor.draft.length),0);await click(400,100);await click(500,100);await click(500,200);await click(400,100);
    assert.equal(await page.evaluate(()=>editor.areas.length),2);await page.evaluate(()=>editor.rename('x-06'));assert.equal(await page.evaluate(()=>editor.pins.length),1);
    await page.evaluate(()=>editor.zoom(2));
    const box=await page.locator('.pe-canvas').boundingBox();await page.mouse.move(box.x+400,box.y+200);await page.mouse.down({button:'middle'});await page.mouse.move(box.x+250,box.y+200);await page.mouse.up({button:'middle'});assert(await page.evaluate(()=>editor.container.scrollLeft)>0);await page.evaluate(()=>editor.fit());
    const serialized=await page.evaluate(()=>editor.serialize());assert(serialized.includes('r="0.4"'));assert.equal((serialized.match(/data-i="x-06"/g)||[]).length,2);
    await page.screenshot({path:path.join(output,'editor-check.png')});
    await page.evaluate(()=>{editor.start();editor.ortho=true;});
    await click(550,330);await click(700,350);await click(680,440);await click(550,330);
    assert(await page.evaluate(()=>{var pts=editor.areas[editor.areas.length-1].points;return pts.every((p,i)=>{var q=pts[(i+1)%pts.length];return Math.abs(p[0]-q[0])<.001||Math.abs(p[1]-q[1])<.001;});}));
    await page.keyboard.press('Control+z');assert.equal(await page.evaluate(()=>editor.areas.length),2);assert.equal(await page.evaluate(()=>editor.draft.length),3);
        schemes=[{id:1,title:'Типовая',section:'2-A',image:{url:'/image.png',path:'plans/test.png',width:800,height:500},assignments:[],floor_labels:[],revision:1,show_pins:1,svg_code:serialized.replace(/x-06/g,'x2-A-06')}];
    await page.goto(base+'/');assert.equal(await page.evaluate(()=>Ext.getCmp('modx-resource-tabs').getActiveTab().title),'Ресурс');assert.equal(await page.locator('.pe-scheme-grid').count(),0);await page.getByText('Планировки',{exact:true}).click();await page.getByText('Типовая',{exact:true}).click();await page.locator('.pe-scheme-grid button[data-act=edit]').first().click();
    await page.waitForSelector('.pe-editor-window.zoom-in');await page.waitForTimeout(300);await page.waitForSelector('.pe-canvas svg');assert.equal(await page.locator('.pe-area').count(),2);
    await page.locator('.pe-area').first().click({position:{x:35,y:35}});assert.equal(await page.locator('.pe-properties input').first().inputValue(),'06');await page.locator('.pe-properties input').first().fill('07');await page.locator('.pe-properties input').first().press('Tab');assert.equal(await page.locator('.pe-properties input').nth(1).inputValue(),'x2-A-07');await page.locator('.pe-properties input').nth(1).fill('x-07');await page.locator('.pe-properties input').nth(1).press('Tab');
    await page.locator('.pp-tools .pp-tool').last().click();
    await page.screenshot({path:path.join(output,'planpoint-editor.png')});
    await page.keyboard.press('n');await page.locator('.pe-canvas svg').click({position:{x:70,y:70}});await page.locator('.pe-canvas svg').click({position:{x:170,y:70}});assert.equal(await page.locator('.pe-draft').count(),1);await page.keyboard.press('Escape');assert.equal(await page.locator('.pe-draft').count(),0);assert.equal(await page.locator('.pe-area').count(),2);await page.keyboard.press('Escape');
    // Draft cancellation must retain the two saved areas; save shortcut avoids the browser Save dialog.
    await page.keyboard.press('Control+s');await page.waitForTimeout(200);assert(calls.some(c=>c.op==='save'));
    assert(await page.getByText(/Сохранено в/).count());
    await page.getByText('Изображение',{exact:true}).click();
    for(let repeat=0;repeat<2;repeat++){
        await page.getByText('Заменить изображение',{exact:true}).click();
        
        await page.evaluate(()=>{var panel;Ext.ComponentMgr.all.each(function(c){if(c instanceof mixedimage.panel)panel=c;});if(!panel)throw Error('Missing real mixedImage panel');if(panel.removeFile!==false||panel.tvId==='1')throw Error('Unsafe shared TV config');panel.getInput().handleTrigger__manager();testBrowser.select({relativeUrl:'plans/replaced.png'});});
        await page.waitForTimeout(100);await page.getByText('Использовать изображение',{exact:true}).click();
    }
    assert.equal(await page.locator('.pe-canvas svg').getAttribute('viewBox'),'0 0 1200 700');assert.equal(await page.evaluate(()=>browserCreations),1);assert.equal(await page.evaluate(()=>resourceChanges),0);
    await page.getByText('Редактор',{exact:true}).click();await page.keyboard.press('Control+z');await page.keyboard.press('Control+z');assert.equal(await page.locator('.pe-canvas svg').getAttribute('viewBox'),'0 0 800 500');await page.keyboard.press('Control+Shift+z');await page.keyboard.press('Control+Shift+z');assert.equal(await page.locator('.pe-canvas svg').getAttribute('viewBox'),'0 0 1200 700');await page.getByText('Код SVG',{exact:true}).click();await page.getByText('Редактировать SVG · Ace',{exact:true}).click();await page.waitForSelector('.ace_editor');assert(await page.evaluate(()=>ace.edit(document.querySelector('.ace_editor')).getValue().includes('x-07')));await page.getByText('Применить',{exact:true}).click();await page.waitForTimeout(150);assert(calls.some(c=>c.op==='validate'));await page.getByText('Редактор',{exact:true}).click();await page.keyboard.press('Control+s');await page.waitForTimeout(150);await page.getByText('Закрыть',{exact:true}).click();
    await page.screenshot({path:path.join(output,'planpoint-list.png')});
    const search=page.locator('.pe-scheme-grid input.x-form-text').first();await search.fill('Нет совпадений');await search.press('Enter');await page.getByText('Добавьте первую схему',{exact:true}).waitFor();await search.fill('');await search.press('Enter');await page.getByText('Типовая',{exact:true}).waitFor();
    await page.locator('.pe-scheme-grid button[data-act=assign]').first().click();await page.getByText('10',{exact:true}).waitFor();
    await page.getByText('Сохранить назначения',{exact:true}).click();await page.waitForTimeout(200);assert(calls.some(c=>c.op==='assign'));
    await page.locator('.pe-scheme-grid button[data-act=copy]').first().click();await page.getByText('Копировать 1 схем',{exact:true}).waitFor();await page.getByText('Отмена',{exact:true}).click();
    await page.locator('.pe-scheme-grid button[data-act=preview]').first().click();await page.waitForSelector('.pe-preview');await page.evaluate(()=>Ext.WindowMgr.getActive().close());
    assert.deepEqual(errors,[]);console.log('Browser: continuous drawing, Enter ignored, shared pin, pan/fit, native MODX tab, direct key, actions/search/Ctrl+S/assign/copy/preview, Ace import, mixedImage isolation and repeated browser OK');
}finally{await browser.close();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
