/*!
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package local_mulima_analytics
 * @copyright 2026 Joaquim Pascoal Mulima Junior
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Execute the delivered inline code and course picker in Chromium, with simulated Moodle responses.
const fs = require('node:fs');
const path = require('node:path');
const { chromium: playwright } = require('playwright');

const categories = [
  {id:1,parent:0,name:'ANO LECTIVO 2026'}, {id:2,parent:0,name:'ANO LECTIVO 2025'},
  {id:10,parent:1,name:'Departamentos'}, {id:11,parent:1,name:'Categoria vazia'},
  {id:20,parent:10,name:'DTC'}, {id:21,parent:10,name:'DTM'},
  {id:30,parent:20,name:'Informática <A & B>'}, {id:31,parent:20,name:'Telecomunicações'},
  {id:40,parent:30,name:'Semestre 1'}
];
const courses = [
  {id:101,category:1,name:'Directa no período'}, {id:102,category:10,name:'Directa em Departamentos'},
  {id:103,category:20,name:'Directa em DTC'}, {id:104,category:30,name:'Programação <A & B>'},
  {id:105,category:40,name:'Algoritmos'}, {id:106,category:31,name:'Redes'},
  {id:107,category:21,name:'Mecânica'}, {id:108,category:2,name:'Histórico'}
].map(c => ({...c,short:'C'+c.id,cat:categories.find(k=>k.id===c.category).name,views:10,students:2,enrolled:3}));
function beneath(id, root) {
  while(id) { if(id===root)return true; id=categories.find(c=>c.id===id)?.parent||0; }
  return false;
}
const scoped = root => courses.filter(c=>beneath(c.category, Number(root)));
function render(root, tab='acessos', language='pt') {
  const languageSource=fs.readFileSync(path.join(root,'lang',language,'local_mulima_analytics.php'),'utf8');
  let strings=Object.fromEntries([...languageSource.matchAll(/\$string\['([^']+)'\] = '((?:\\.|[^'\\])*)';/g)].map(m=>[m[1],m[2].replace(/\\'/g,"'").replace(/\\\\/g,'\\')]));
  let source = fs.readFileSync(path.join(root,tab+'.php'),'utf8');
  const layout = fs.readFileSync(path.join(root,'die_layout.php'),'utf8');
  source = source.slice(source.indexOf('?>')+2);
  source = source.replace('<?php echo json_encode($COURSE_VIEW); ?>', JSON.stringify('http://learning.test/course/view.php'));
  source = source.replace('<?php echo json_encode($FORUM_VIEW); ?>', JSON.stringify('http://learning.test/mod/forum/view.php'));
  source = source.replace('<?php echo json_encode($scoring); ?>', JSON.stringify({weight_activities:3,weight_resources:2,weight_grading:4,weight_forums:1,maximum:99,moderate:20,high:60}));
  source = source.replace(/<\?php foreach \(\$root_cats[\s\S]*?<\?php endforeach; \?>/g,
    categories.filter(c=>!c.parent).map(c=>`<option value="${c.id}">${c.name}</option>`).join(''));
  source = source.replace(/<\?php echo \$(AX|SK|XLS|EXP|MSG|maxpoints); \?>/g, (_,n)=>({AX:'/'+tab+'_ajax.php',SK:'test',XLS:'/'+tab+'_export.php',EXP:'/'+tab+'_export.php',MSG:'/message.php',maxpoints:'800'}[n]));
  source = source.replace(/<\?php echo s\(get_string\('([^']+)'\s*,\s*'local_mulima_analytics'(?:,\s*(\d+))?\)\); \?>/g,
    (_,key,number)=>(strings[key]||key).replace(/\{\$a\}/g,number||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'));
  source = source.replace(/<\?php[\s\S]*?\?>/g, '');
  // When available, exercise templates rendered by the production PHP itself.
  const fixtureDirectory=process.env.LANGUAGE_FIXTURE_DIR;
  if(fixtureDirectory){
    source=fs.readFileSync(path.join(fixtureDirectory,tab+'-'+language+'.html'),'utf8');
    strings=JSON.parse(fs.readFileSync(path.join(fixtureDirectory,'dictionary-'+language+'.json'),'utf8'));
  }else{
    strings.locale=language;
    strings.module_names=Object.fromEntries([...layout.matchAll(/'([^']+)' => get_string\('([^']+)', 'local_mulima_analytics'\)/g)].map(m=>[m[1],strings[m[2]]]));
  }
  const scripts = [...layout.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m=>m[1]);
  const picker = scripts.filter(s=>s.includes('window.DieCourseSearch =')).at(-1);
  const tree = scripts.find(s=>s.includes('window.LearningAnalyticsCategoryTree =')) || '';
  const shared=scripts.find(s=>s.includes('function dieText('))||'';
  const helpers=shared.slice(shared.indexOf('function dieText('));
  const filters = tab==='acessos'?'':fs.readFileSync(path.join(root,'filter_controls.php'),'utf8').replace(/<\?php[\s\S]*?\?>/g,'');
  const css = fs.readFileSync(path.join(root,'styles.css'),'utf8') + [...layout.matchAll(/<style>([\s\S]*?)<\/style>/g)].map(m=>m[1].replace(/<\?php[\s\S]*?\?>/g,'#0369a1')).join('\n');
  return `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body>
    <script>window.DIE_LANG=${JSON.stringify(strings).replace(/</g,'\\u003c')};${helpers};window.__toasts=[];window.dieToast=function(message){window.__toasts.push(message);};window.__exports=[];window.open=function(url){window.__exports.push(url);};</script>
    <script>${tree}</script><script>${picker}</script>${filters}<div id="die-root"><div><main>${source}</body></html>`;
}
async function launch() { return playwright.launch({headless:true,executablePath:process.env.CHROMIUM_EXECUTABLE_PATH}); }
async function fixture(browser, root, options={}) {
  const tab=options.tab||'acessos';
  const available=options.courses||courses;
  const selected=p=>available.filter(c=>(Number(p.descendants)===1?beneath(c.category,Number(p.catid||p.period)):c.category===Number(p.catid||p.period))&&(!p.courseid||String(c.id)===String(p.courseid)));
  const page = await browser.newPage({viewport:{width:1450,height:900}});
  const requests=[], errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('http://learning.test/**', async route=>{
    const u = new URL(route.request().url());
    if(u.pathname==='/'+tab+'.php')return route.fulfill({contentType:'text/html',body:render(root,tab,options.language||'pt')});
    const p={...Object.fromEntries(u.searchParams),...(route.request().postData()?JSON.parse(route.request().postData()):{})}; requests.push(p);
    const custom=await options.respond?.(p);
    if(custom?.delay)await new Promise(r=>setTimeout(r,custom.delay));
    if(custom?.status)return route.fulfill({status:custom.status,contentType:custom.contentType||'text/html',body:custom.body||'Login required'}).catch(()=>{});
    let data;
    if(custom?.data)data=custom.data;
    else if(p.op==='cats') data={ok:true,cats:categories.filter(c=>c.parent===Number(p.parent||p.period)).map(c=>({...c,count:scoped(c.id).length,haschildren:categories.some(k=>k.parent===c.id)}))};
    else if(p.op==='courses'||p.op==='panoramic')data={ok:true,courses:selected(p)};
    else if(p.op==='groups')data={ok:true,groups:[]};
    else if(p.op==='activities')data={ok:true,activities:[{cmid:501,name:'Teste '+p.courseid,modname:'quiz',section_name:'Secção 1',total_views:10,unique_students:2,enrolled:3}],stats:{enrolled:3,students:2,total_views:10}};
    else if(p.op==='overview')data={ok:true,courses:selected(p).map(c=>({...c,points:c.id===104?800:400,n_items:2,graded:1,submitted:2,pending:1}))};
    else if(p.op==='acts')data={ok:true,acts:[{itemid:601,name:'Trabalho '+p.courseid,module:'assign',grademax:800,submitted:2,graded:1,pending:1,grade_url:'/mod/assign/view.php?id=601'}]};
    else if(p.op==='risk'){
      const matches=selected(p);
      data={ok:true,students:matches.map(c=>({userid:c.id,fullname:'Estudante '+c.id,email:'s'+c.id+'@example.test',phone:null,coursename:c.name,catname:c.cat,last_access:null,days_since:20})),stats:{critical:0,alert:matches.length,warn:0}};
    }
    else if(p.op==='teachers')data={ok:true,teachers:selected(p).filter(c=>!p.courseid||String(c.id)===String(p.courseid)).map(c=>({userid:c.id,fullname:'Docente '+c.id,email:'t'+c.id+'@example.test',courses:[c.name],course_details:[{id:c.id,name:c.name}],activities:3,resources:1,graded:2,assignments_total:1,submissions_total:4,submissions_graded:2,submissions_pending:2,grading_percent:50,grading_level:'in_progress',assignments:[{id:c.id,name:'Trabalho '+c.id,coursename:c.name,courseid:c.id,submitted:4,corrected:2,pending:2,percent:50,level:'in_progress',team:false,offline:false}],forums_total:3,forums_participated:1,forum_posts:1,forum_discussions:0,forum_replies:1,forum_last_ts:1000000000,forum_last_post:'01/09/2026 10:00',last_ts:1000000000,last_access:'01/09/2026',score:20}))};
    else if(p.op==='detail'&&tab==='cobertura')data={ok:true,grademax:800,students:[{fullname:'Estudante Teste',email:'teste@example.test',graded:false,grade:null,days_wait:10,submitted_at:'01/09/2026'}]};
    else if(p.op==='detail')data={ok:true,students:[{fullname:'Estudante de teste',email:'teste@example.test',views:10,first_access:'01/09/2026',last_access:'17/09/2026'}],total_views:10,first_overall:'01/09/2026'};
    else data={ok:false,error:'Unsupported operation'};
    return route.fulfill({contentType:'application/json',body:JSON.stringify(data)}).catch(()=>{});
  });
  await page.goto('http://learning.test/'+tab+'.php');
  return {page, requests, errors};
}
module.exports={launch,fixture,courses,scoped};
