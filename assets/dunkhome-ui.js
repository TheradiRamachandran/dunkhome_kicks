(function(){
  const key='dunkhome-theme';
  const body=document.body;
  const saved=localStorage.getItem(key);
  if(saved==='light') body.classList.add('light');
  function update(btn){btn.textContent=body.classList.contains('light')?'🌙':'☀️';btn.setAttribute('aria-label',body.classList.contains('light')?'Switch to dark theme':'Switch to light theme');}
  let btn=document.getElementById('themeToggle');
  if(!btn){btn=document.createElement('button');btn.type='button';btn.id='themeToggle';btn.className='dh-theme-trigger';btn.title='Toggle theme';document.body.appendChild(btn);}
  update(btn);
  btn.addEventListener('click',function(){body.classList.toggle('light');localStorage.setItem(key,body.classList.contains('light')?'light':'dark');update(btn);});
  const overlay=document.createElement('div');overlay.className='dh-page-transition';overlay.setAttribute('role','status');overlay.setAttribute('aria-label','Loading page');
  const loader=document.createElement('div');loader.className='dh-loader';loader.setAttribute('aria-hidden','true');overlay.appendChild(loader);document.body.prepend(overlay);
  const loaderStartedAt=performance.now();
  const hideOverlay=()=>window.setTimeout(()=>overlay.classList.add('hide'),Math.max(0,550-(performance.now()-loaderStartedAt)));
  if(document.readyState==='complete')hideOverlay();else window.addEventListener('load',hideOverlay,{once:true});
  document.querySelectorAll('a[href]').forEach(a=>{const href=a.getAttribute('href');if(!href||href.startsWith('#')||href.startsWith('javascript:')||a.target==='_blank')return;a.addEventListener('click',e=>{if(e.defaultPrevented)return;const url=new URL(href,location.href);if(url.origin!==location.origin)return;e.preventDefault();overlay.classList.remove('hide');setTimeout(()=>location.href=url.href,220)})});
  document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity()){overlay.classList.remove('hide')}}));
})();
