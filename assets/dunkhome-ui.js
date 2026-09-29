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
  const overlay=document.createElement('div');overlay.className='dh-page-transition';document.body.prepend(overlay);requestAnimationFrame(()=>overlay.classList.add('hide'));
  document.querySelectorAll('a[href]').forEach(a=>{const href=a.getAttribute('href');if(!href||href.startsWith('#')||href.startsWith('javascript:')||a.target==='_blank')return;a.addEventListener('click',e=>{if(e.defaultPrevented)return;const url=new URL(href,location.href);if(url.origin!==location.origin)return;e.preventDefault();overlay.classList.remove('hide');setTimeout(()=>location.href=url.href,220)})});
  document.querySelectorAll('form').forEach(f=>f.addEventListener('submit',()=>{if(f.checkValidity()){overlay.classList.remove('hide')}}));
})();
