/*!
 * Copyright 2026 Joaquim Pascoal Mulima Junior
 * SPDX-License-Identifier: GPL-3.0-or-later
 * @package local_mulima_analytics
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {launch,fixture,courses}=require('./fixture.cjs');
const root=path.resolve(__dirname,'../..');
const configs={
  acessos:{period:'#sel_period',level:'#access_category_',rows:'#courses_body tr',labels:['Views','Average/student','Activity']},
  cobertura:{period:'#flt_period',level:'#report_category_',rows:'#cb_tbody tr',labels:['Score target','Assessment by course']},
  risco:{period:'#flt_period',level:'#report_category_',rows:'#rsk_tbody tr',labels:['Access scope','Platform overall','No access for more than']},
  docentes:{period:'#flt_period',level:'#report_category_',rows:'#doc_tbody tr',labels:['Period (days)','Teacher posts','Copy emails']},
  presenca:{period:'#flt_period',level:'#report_category_',labels:['Exam session','Resit Exam','Scope and export','Export Excel']},
};
async function leaf(page,cfg){
  await page.selectOption(cfg.period,'1');
  await page.selectOption(cfg.level+'1','10');
  await page.selectOption(cfg.level+'2','20');
  await page.selectOption(cfg.level+'3','30');
  if(cfg.rows)await page.waitForFunction(s=>document.querySelectorAll(s).length===1,cfg.rows);
  else await page.waitForFunction(()=>!document.querySelector('#btn_xls').disabled);
}
(async()=>{
  const browser=await launch();let passed=0;
  try{
    for(const language of ['en','pt'])for(const [tab,cfg] of Object.entries(configs)){
      const f=await fixture(browser,root,{tab,language,courses:courses.filter(c=>c.id>=104)}),page=f.page;
      page.setDefaultTimeout(8000);
      try{
        assert.equal(await page.locator(cfg.period).inputValue(),'');
        assert.equal(await page.locator(cfg.level+'1').inputValue(),'');
        await leaf(page,cfg);
        assert.equal(await page.locator(cfg.level+'3').evaluate(e=>e.previousElementSibling.textContent),language==='en'?'Category 3':'Categoria 3');
        if(tab!=='cobertura'){
          assert((await page.locator('#dcs_inp').getAttribute('placeholder')).includes(language==='en'?'Search among':'Pesquisar entre'));
          await page.locator('#dcs_inp').focus();
          assert.equal(await page.locator('.dcs-drop-item.all-opt').innerText(),language==='en'?'All courses (1)':'Todas as disciplinas (1)');
          await page.locator('#dcs_inp').fill('not-a-course');
          await page.waitForFunction(()=>document.querySelector('.dcs-drop-empty'));
          assert((await page.locator('.dcs-drop-empty').innerText()).includes(language==='en'?'No course matches':'Nenhuma disciplina corresponde'));
          await page.locator('#dcs_inp').fill('');
        }
        if(language==='en'){
          const visible=await page.locator('body').innerText();
          for(const label of cfg.labels){assert(visible.includes(label)||await page.locator('body').textContent().then(t=>t.includes(label)),tab+': '+label);}
          assert(!/Tentar novamente|A carregar|Meta de pontuação|Copiar emails|Últimos \d+ dias|Sem acesso há mais de|Avaliação por disciplina/.test(visible),tab+': Portuguese interface label remains');
        }
        if(tab==='acessos'){
          await page.locator('.crs-ver').first().click();await page.locator('#act_body .ac-ver').first().waitFor();
          assert((await page.locator('#act_body').innerText()).includes(language==='en'?'Quiz':'Teste'));
          await page.locator('.ac-ver').first().click();await page.locator('#m_content').waitFor({state:'visible'});
          assert.equal(await page.locator('#m_info').innerText(),language==='en'?'Views since the platform went live':'Visualizações desde o início da plataforma');
        }else if(tab==='cobertura'){
          await page.locator('.cb-drill').first().click();await page.locator('.cb-det').first().waitFor();
          assert((await page.locator('#cb_acts_tbody').innerText()).toLowerCase().includes(language==='en'?'needs attention':'requer atenção'));
          await page.locator('.cb-det').first().click();await page.locator('#m_tbl').waitFor({state:'visible'});
          assert((await page.locator('#m_stats').innerText()).toLowerCase().includes(language==='en'?'pending grading':'por avaliar'));
        }else if(tab==='risco'){
          await page.selectOption('#flt_scope','courses');await page.waitForFunction(text=>document.querySelector('#rsk_dist_lbl').textContent.includes(text),language==='en'?'student/course records':'registos por disciplina');
          assert((await page.locator('#rsk_dist_lbl').innerText()).includes(language==='en'?'student/course records':'registos por disciplina'));
        }else if(tab==='docentes'){
          assert.equal(await page.locator('#flt_range option:checked').innerText(),language==='en'?'Last 30 days':'Últimos 30 dias');
        }else if(tab==='presenca'){
          await page.locator('#dcs_inp').focus();await page.locator('.dcs-drop-name').click();
          await page.waitForFunction(()=>document.querySelector('#scope_hint_txt').textContent.includes('Programação'));
          assert((await page.locator('#scope_hint_txt').innerText()).includes(language==='en'?'List for':'Lista de'));
        }
        assert.deepEqual(f.errors,[]);passed++;console.log('PASS '+tab+' '+language+': rendered labels, picker and dynamic results');
      }finally{await page.close();}
    }
    for(const tab of ['acessos','cobertura','risco','docentes','presenca']){
      const f=await fixture(browser,root,{tab,language:'en',respond:p=>p.op==='courses'?{status:500}:undefined});
      try{
        await f.page.selectOption(configs[tab].period,'1');
        const selector=tab==='acessos'?'#access_error':'.la-filter-error';
        await f.page.locator(selector).waitFor({state:'visible'});
        assert((await f.page.locator(selector).innerText()).includes('Could not load the data.'));
        assert((await f.page.locator(selector).innerText()).includes('Try again'));
        assert.deepEqual(f.errors,[]);passed++;console.log('PASS '+tab+' en: actionable translated network error');
      }finally{await f.page.close();}
    }
    const f=await fixture(browser,root,{language:'en'});
    try{
      for(const variant of ['src/common.js','build/common.min.js']){
        await f.page.evaluate(()=>{window.define=(deps,factory)=>{window.moduleUnderTest=factory();};});
        await f.page.addScriptTag({content:fs.readFileSync(path.join(root,'amd',variant),'utf8')});
        for(const [close,invalid] of [['Close','Invalid server response.'],['Fechar','Resposta inválida do servidor.']]){
          const label=await f.page.evaluate(({close,invalid})=>{
            const wrap=document.createElement('div');wrap.id='die-toast-wrap';document.body.appendChild(wrap);
            moduleUnderTest.init({close,invalid_response:invalid});dieToast('test');const result=wrap.querySelector('button').getAttribute('aria-label');wrap.remove();return result;
          },{close,invalid});assert.equal(label,close);
        }
        passed++;console.log('PASS AMD '+variant+': localised controls');
      }
      assert.deepEqual(f.errors,[]);
    }finally{await f.page.close();}
    console.log(`RESULT ${passed} localisation browser scenarios passed.`);
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
