(function(){
  const key='dunkhome-theme';
  const body=document.body;
  let saved='dark';
  try { saved=localStorage.getItem(key) || 'dark'; } catch (e) {}
  if(saved==='light') body.classList.add('light');
  function update(btn){
    if(!btn) return;
    const isLight = body.classList.contains('light');
    btn.textContent = isLight ? '🌙' : '☀️';
    btn.setAttribute('aria-label', isLight ? 'Switch to dark theme' : 'Switch to light theme');
    btn.setAttribute('aria-pressed', String(isLight));
    btn.title = isLight ? 'Switch to dark theme' : 'Switch to light theme';
  }
  let btn=document.getElementById('themeToggle');
  if(!btn){btn=document.createElement('button');btn.type='button';btn.id='themeToggle';btn.className='dh-theme-trigger';btn.title='Toggle theme';document.body.appendChild(btn);}
  const drawerTheme=document.getElementById('drawerThemeToggle');
  const updateThemeButtons=()=>{update(btn);update(drawerTheme);};
  const toggleTheme=()=>{
    body.classList.toggle('light');
    try { localStorage.setItem(key,body.classList.contains('light')?'light':'dark'); } catch (e) {}
    updateThemeButtons();
  };
  if(btn.dataset.themeManaged !== 'true'){
    btn.dataset.themeManaged='true';
    btn.onclick=function(){toggleTheme();return false;};
  }
  updateThemeButtons();
  if(drawerTheme&&drawerTheme.dataset.themeManaged!=='true'){
    drawerTheme.dataset.themeManaged='true';
    drawerTheme.addEventListener('click',toggleTheme);
  }
  const menu=document.getElementById('menuBtn');
  const nav=document.getElementById('navLinks');
  const scrim=document.getElementById('navScrim');
  const drawerClose=document.getElementById('drawerCloseBtn');
  if(menu&&nav&&menu.dataset.menuManaged!=='true'){
    menu.dataset.menuManaged='true';
    const closeMenu=()=>{
      nav.classList.remove('active');
      if(scrim)scrim.classList.remove('active');
      body.classList.remove('dh-nav-open');
      menu.textContent='☰';
      menu.setAttribute('aria-expanded','false');
      menu.setAttribute('aria-label','Open menu');
      menu.removeAttribute('aria-hidden');
    };
    menu.addEventListener('click',()=>{
      const isOpen=nav.classList.toggle('active');
      if(scrim)scrim.classList.toggle('active',isOpen);
      body.classList.toggle('dh-nav-open',isOpen);
      menu.textContent='☰';
      menu.setAttribute('aria-expanded',String(isOpen));
      menu.setAttribute('aria-label',isOpen?'Close menu':'Open menu');
      if(isOpen)menu.setAttribute('aria-hidden','true');
      else menu.removeAttribute('aria-hidden');
    });
    if(drawerClose)drawerClose.addEventListener('click',()=>{closeMenu();menu.focus();});
    if(scrim)scrim.addEventListener('click',closeMenu);
    nav.querySelectorAll('a').forEach(link=>link.addEventListener('click',closeMenu));
    document.addEventListener('click',event=>{
      if(nav.classList.contains('active')&&!nav.contains(event.target)&&!menu.contains(event.target))closeMenu();
    });
    document.addEventListener('keydown',event=>{
      if(event.key==='Escape'&&nav.classList.contains('active')){closeMenu();menu.focus();}
    });
  }

  const resetForm=document.querySelector('form input[name="action"][value="reset_password"]')?.form;
  const resetPassword=resetForm?.querySelector('input[name="password"]');
  const passwordConfirmation=resetForm?.querySelector('input[name="confirm_password"]');
  if(resetForm&&resetPassword&&passwordConfirmation){
    const requestForm=document.querySelector('form input[name="action"][value="send_reset_otp"]')?.form;
    const requestEmail=requestForm?.querySelector('input[name="email"]');
    const resetEmail=resetForm.querySelector('input[name="email"]');
    if(requestEmail&&resetEmail&&requestEmail.value&&requestEmail.value===resetEmail.value){
      resetEmail.readOnly=true;
      resetEmail.title='This email address is linked to your verification code.';
    }

    const otpInput=resetForm.querySelector('input[name="otp"]');
    if(otpInput){
      const otpGroup=document.createElement('div');
      otpGroup.className='recovery-otp-group';
      otpGroup.setAttribute('role','group');
      otpGroup.setAttribute('aria-label','Enter the 6-digit verification code');
      const digits=[];
      otpInput.type='hidden';
      otpInput.required=false;
      otpInput.autocomplete='off';
      otpInput.insertAdjacentElement('afterend',otpGroup);
      for(let index=0;index<6;index++){
        const digit=document.createElement('input');
        digit.className='recovery-otp-digit';
        digit.type='text';
        digit.inputMode='numeric';
        digit.pattern='[0-9]';
        digit.maxLength=1;
        digit.required=true;
        digit.autocomplete=index===0?'one-time-code':'off';
        digit.setAttribute('aria-label',`Verification code digit ${index+1}`);
        digit.setAttribute('aria-posinset',String(index+1));
        digit.setAttribute('aria-setsize','6');
        otpGroup.append(digit);
        digits.push(digit);
      }
      const syncOtp=()=>{
        otpInput.value=digits.map(digit=>digit.value).join('');
      };
      const fillOtp=value=>{
        const numbers=value.replace(/\D/g,'').slice(0,6);
        digits.forEach((digit,index)=>{digit.value=numbers[index]||'';});
        syncOtp();
        digits[Math.min(numbers.length,5)].focus();
      };
      digits.forEach((digit,index)=>{
        digit.addEventListener('input',()=>{
          const value=digit.value.replace(/\D/g,'');
          if(value.length>1){
            fillOtp(value);
            return;
          }
          digit.value=value;
          syncOtp();
          if(value&&digits[index+1])digits[index+1].focus();
        });
        digit.addEventListener('keydown',event=>{
          if(event.key==='Backspace'&&!digit.value&&digits[index-1]){
            digits[index-1].focus();
          }else if(event.key==='ArrowLeft'&&digits[index-1]){
            event.preventDefault();
            digits[index-1].focus();
          }else if(event.key==='ArrowRight'&&digits[index+1]){
            event.preventDefault();
            digits[index+1].focus();
          }
        });
        digit.addEventListener('paste',event=>{
          const pasted=event.clipboardData?.getData('text')||'';
          if(/\d/.test(pasted)){
            event.preventDefault();
            fillOtp(pasted);
          }
        });
      });
      resetForm.addEventListener('submit',event=>{
        syncOtp();
        if(otpInput.value.length!==6){
          event.preventDefault();
          digits.find(digit=>!digit.value)?.focus();
        }
      });
    }

    const requirements=[
      {label:'At least 8 characters',test:value=>value.length>=8},
      {label:'One uppercase letter',test:value=>/[A-Z]/.test(value)},
      {label:'One number',test:value=>/\d/.test(value)},
      {label:'One special character',test:value=>/[^A-Za-z0-9]/.test(value)}
    ];
    const checks=document.createElement('ul');
    checks.className='recovery-password-checks';
    checks.setAttribute('aria-label','Password requirements');
    const checkItems=requirements.map(({label})=>{
      const item=document.createElement('li');
      item.textContent=label;
      checks.append(item);
      return item;
    });
    resetPassword.insertAdjacentElement('afterend',checks);

    const feedback=document.createElement('p');
    feedback.className='recovery-password-feedback';
    feedback.setAttribute('aria-live','polite');
    passwordConfirmation.insertAdjacentElement('afterend',feedback);

    const addPasswordToggle=input=>{
      const control=document.createElement('div');
      control.className='recovery-password-control';
      input.parentNode.insertBefore(control,input);
      control.append(input);
      const toggle=document.createElement('button');
      toggle.className='recovery-password-toggle';
      toggle.type='button';
      const eyeIcon='<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M2.5 12s3.3-6 9.5-6 9.5 6 9.5 6-3.3 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.7"/></svg>';
      const eyeOffIcon='<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6.2 0 9.5 6 9.5 6a16.6 16.6 0 0 1-3.1 3.7M6.2 6.3C3.8 7.9 2.5 12 2.5 12s3.3 6 9.5 6a9.9 9.9 0 0 0 3.1-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      toggle.innerHTML=eyeIcon;
      toggle.title='Show password';
      toggle.setAttribute('aria-label','Show password');
      toggle.setAttribute('aria-pressed','false');
      toggle.addEventListener('click',()=>{
        const visible=input.type==='password';
        input.type=visible?'text':'password';
        toggle.innerHTML=visible?eyeOffIcon:eyeIcon;
        toggle.title=visible?'Hide password':'Show password';
        toggle.setAttribute('aria-label',toggle.title);
        toggle.setAttribute('aria-pressed',String(visible));
        input.focus();
      });
      control.append(toggle);
    };
    addPasswordToggle(resetPassword);
    addPasswordToggle(passwordConfirmation);

    const updatePasswordFeedback=()=>{
      const value=resetPassword.value;
      const results=requirements.map(({test})=>test(value));
      checkItems.forEach((item,index)=>item.classList.toggle('is-met',results[index]));
      resetPassword.setAttribute('aria-invalid',String(value.length>0&&!results.every(Boolean)));
      feedback.classList.remove('is-match','is-mismatch');
      if(passwordConfirmation.value===''){
        feedback.textContent='';
        passwordConfirmation.removeAttribute('aria-invalid');
      }else if(value===passwordConfirmation.value){
        feedback.textContent='Your passwords match.';
        feedback.classList.add('is-match');
        passwordConfirmation.setAttribute('aria-invalid','false');
      }else{
        feedback.textContent='Your passwords do not match yet.';
        feedback.classList.add('is-mismatch');
        passwordConfirmation.setAttribute('aria-invalid','true');
      }
    };
    resetPassword.addEventListener('input',updatePasswordFeedback);
    passwordConfirmation.addEventListener('input',updatePasswordFeedback);
  }

  const userHeader=document.querySelector('.dh-user-header');
  if(userHeader){
    let pageLoader=document.querySelector('.dh-page-transition');
    if(!pageLoader){
      pageLoader=document.createElement('div');
      pageLoader.className='dh-page-transition';
      pageLoader.setAttribute('role','status');
      pageLoader.setAttribute('aria-live','polite');
      pageLoader.setAttribute('aria-label','Loading page');

      const card=document.createElement('div');
      card.className='dh-loading-card dh-loading-spinner-only';
      const spinner=document.createElement('span');
      spinner.className='dh-loader';
      spinner.setAttribute('aria-hidden','true');
      card.append(spinner);
      pageLoader.append(card);
      document.body.prepend(pageLoader);
    }

    let loaderHidden=false;
    let loaderShownAt=Date.now();
    const hidePageLoader=()=>{
      if(loaderHidden)return;
      loaderHidden=true;
      pageLoader.classList.add('hide');
      pageLoader.setAttribute('aria-hidden','true');
    };
    const showPageLoader=()=>{
      if(!loaderHidden)return;
      loaderHidden=false;
      loaderShownAt=Date.now();
      pageLoader.setAttribute('aria-hidden','false');
      pageLoader.classList.remove('hide');
    };

    const finishInitialLoad=()=>{
      window.setTimeout(hidePageLoader,Math.max(0,260-(Date.now()-loaderShownAt)));
    };
    if(document.readyState==='interactive'||document.readyState==='complete'){
      finishInitialLoad();
    }else{
      document.addEventListener('DOMContentLoaded',finishInitialLoad,{once:true});
    }
    window.addEventListener('pageshow',event=>{
      if(event.persisted)hidePageLoader();
    });
    window.setTimeout(hidePageLoader,5000);

    document.addEventListener('click',event=>{
      if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
      const link=event.target instanceof Element?event.target.closest('a[href]'):null;
      if(!link||link.target&&link.target!=='_self'||link.hasAttribute('download'))return;
      const destination=new URL(link.href,window.location.href);
      if(destination.origin!==window.location.origin)return;
      if(destination.pathname===window.location.pathname&&destination.search===window.location.search&&destination.hash)return;
      showPageLoader();
    });
    document.addEventListener('submit',event=>{
      window.setTimeout(()=>{
        if(!event.defaultPrevented)showPageLoader();
      },0);
    });
  }
})();
