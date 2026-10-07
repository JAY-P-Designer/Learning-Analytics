<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared hierarchical report filters.
 *
 * @package    local_mulima_analytics
 * @copyright  2026 Joaquim Pascoal Mulima Junior
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
// Shared transport and category selection for Cobertura, Em Risco, Docentes and Presenças.
// Each page supplies its own report callbacks and calculation parameters.
?>
<style>
.la-report-filters .la-category-tree{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}
.la-report-filters select{min-height:38px}
.la-filter-error{margin:10px 0;padding:12px 16px;border:1px solid #fca5a5;border-radius:8px;background:#fef2f2;color:#991b1b;font-size:13px}
.la-filter-error button{margin-left:10px}
@media(max-width:640px){
  #die-root .la-report-filters{grid-template-columns:1fr!important}
  #die-root .la-report-filters>div,#die-root .la-report-filters>#dcs_container{grid-column:1/-1;max-width:none!important}
}
</style>
<script>
window.LearningAnalyticsFilters = (function(){
  'use strict';
  function create(options){
    var period=document.getElementById('flt_period'),category=document.getElementById('flt_cat');
    var tree=document.getElementById('flt_cat_tree'),requests={},errors={},timer=null,ready=false,descendants=0;
    var zone=document.createElement('div');zone.id='la_filter_errors';
    tree.closest('.die-filters').insertAdjacentElement('afterend',zone);
    function clearError(key){if(errors[key]){errors[key].remove();delete errors[key];}}
    function cancel(key){
      var previous=requests[key];delete requests[key];clearError(key);
      if(previous){clearTimeout(previous.timer);previous.controller.abort();}
    }
    function request(key,op,params,done,fail,config){
      cancel(key);config=config||{};
      var pending={controller:new AbortController(),timedout:false};requests[key]=pending;
      pending.timer=setTimeout(function(){pending.timedout=true;pending.controller.abort();},60000);
      var url=new URL(options.url,window.location.href);
      url.searchParams.set('op',op);url.searchParams.set('sesskey',options.sesskey);
      var init={credentials:'same-origin',headers:{Accept:'application/json'},signal:pending.controller.signal};
      if(config.post){init.method='POST';init.headers['Content-Type']='application/json';init.body=JSON.stringify(params);}
      else Object.keys(params).forEach(function(k){url.searchParams.set(k,params[k]);});
      fetch(url.toString(),init).then(function(response){
        if(!response.ok||response.redirected)throw new Error(DIE_LANG.request_failed);
        return response.json();
      }).then(function(data){
        if(requests[key]!==pending)return;
        if(!data||data.ok!==true)throw new Error(data&&data.error||DIE_LANG.invalid_response);
        done(data);
      }).catch(function(error){
        if(requests[key]!==pending)return;
        if(fail)fail();
        var message=pending.timedout?DIE_LANG.request_too_long:
          (error instanceof SyntaxError?DIE_LANG.invalid_json_response:error.message);
        var alert=document.createElement('div');alert.className='la-filter-error';alert.setAttribute('role','alert');
        alert.dataset.request=key;
        var text=document.createElement('span');text.textContent=message;alert.appendChild(text);
        var retry=document.createElement('button');retry.type='button';retry.className='btn btn-secondary btn-sm';retry.textContent=DIE_LANG.retry;
        retry.onclick=function(){request(key,op,params,done,fail,config);};alert.appendChild(retry);
        (config.errorHost?document.getElementById(config.errorHost):zone).appendChild(alert);errors[key]=alert;
      }).finally(function(){clearTimeout(pending.timer);if(requests[key]===pending)delete requests[key];});
    }
    function scope(){return {period:period.value,catid:category.value||'',descendants:descendants,courseid:options.withCourses?DieCourseSearch.getSelectedId()||'':''};}
    function reset(){
      clearTimeout(timer);cancel('report');cancel('detail');
      options.onReset();
    }
    function refresh(){
      reset();
      if(!period.value||!ready)return;
      if(options.onLoading)options.onLoading();
      timer=setTimeout(function(){options.onChange(scope());},180);
    }
    function box(level,text){
      var wrap=document.createElement('div');wrap.className='la-category-level';
      var label=document.createElement('label'),select=document.createElement('select');
      select.id='report_category_'+(level+1);select.className='die-select';select.disabled=true;
      label.htmlFor=select.id;
      label.textContent=DIE_LANG.categoria_nivel.replace('{$a}',level+1);
      select.add(new Option(text,''));wrap.appendChild(label);wrap.appendChild(select);tree.appendChild(wrap);
      return wrap;
    }
    function loadCategories(parent,level,pid){
      var wrap=box(level,DIE_LANG.a_carregar),select=wrap.querySelector('select');
      request('categories','cats',{period:pid,parent:parent},function(data){
        var cats=data.cats||[];
        if(!cats.length){if(level)wrap.remove();else select.options[0].textContent='';return;}
        select.innerHTML='';select.add(new Option('',''));
        select.add(new Option(DIE_LANG.todas_neste_nivel,String(parent)));
        cats.forEach(function(c){select.add(new Option(c.name+' ('+Number(c.count||0)+')',String(c.id)));});select.disabled=false;
        select.onchange=function(){
          cancel('categories');while(wrap.nextSibling)wrap.nextSibling.remove();
          var chosen=select.value||String(parent);
          category.value=chosen===String(pid)?'':chosen;
          descendants=select.value===String(parent)?1:0;
          categoryChanged();
          var child=cats.find(function(c){return String(c.id)===select.value;});
          if(child&&child.haschildren!==false)loadCategories(child.id,level+1,pid);
        };
      },function(){select.options[0].textContent=DIE_LANG.erro_carregar;});
    }
    function loadCourses(){
      ready=false;if(options.withCourses)DieCourseSearch.setLoading();
      var selected=scope();
      request('courses','courses',{period:selected.period,catid:selected.catid,descendants:selected.descendants},function(data){
        var list=data.courses||[];ready=list.length>0;
        if(!ready){if(options.withCourses)DieCourseSearch.disable('');return;}
        // The existing picker invokes onAll exactly once for a non-empty list.
        if(options.withCourses)DieCourseSearch.setList(list);else refresh();
      },function(){if(options.withCourses)DieCourseSearch.disable(DIE_LANG.erro_carregar);});
    }
    function categoryChanged(){
      cancel('courses');reset();
      if(!period.value)return;
      loadCourses();
    }
    function periodChanged(){
      cancel('categories');cancel('courses');reset();ready=false;descendants=0;
      category.value='';tree.innerHTML='';
      if(options.withCourses)DieCourseSearch.disable('');
      if(!period.value){box(0,'');return;}
      loadCategories(period.value,0,period.value);
      loadCourses();
    }
    if(options.withCourses){
      DieCourseSearch.mount('dcs_container',{onSelect:refresh,onAll:refresh});
      document.getElementById('dcs_inp').setAttribute('aria-label',DIE_LANG.disciplina);
    }
    return {start:periodChanged,periodChanged:periodChanged,categoryChanged:categoryChanged,
      refresh:refresh,scope:scope,request:request,cancel:cancel,isReady:function(){return ready;}};
  }
  return {create:create};
})();
</script>
