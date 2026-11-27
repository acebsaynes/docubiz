(() => {
 const panel=document.getElementById('dbNotifications');
 const bell=document.querySelector('[data-notification-bell]');
 if(!panel||!bell)return;
 const all=document.getElementById('ntAll'), unread=document.getElementById('ntUnread'), mark=document.getElementById('ntMarkAll'), list=document.getElementById('ntList'), status=document.getElementById('ntStatus'), close=document.getElementById('ntClose');
 const badge=document.createElement('span');badge.className='nt-count';badge.hidden=true;badge.setAttribute('aria-hidden','true');bell.append(badge);bell.classList.add('nt-bell');bell.setAttribute('aria-controls',panel.id);bell.setAttribute('aria-expanded','false');bell.type='button';
 let items=[],csrf='',onlyUnread=false,busy=false,loaded=false;
 function node(tag,cls,txt){const el=document.createElement(tag);el.className=cls;if(txt!==undefined)el.textContent=txt;return el;}
 function age(seconds){const s=Math.max(0,Number(seconds)||0);if(s<60)return 'Just now';if(s<3600)return `${Math.floor(s/60)} min${s>=120?'s':''} ago`;if(s<86400)return `${Math.floor(s/3600)} hr${s>=7200?'s':''} ago`;return `${Math.floor(s/86400)} day${s>=172800?'s':''} ago`;}
 function place(){
  const r=bell.getBoundingClientRect();
  const bar=bell.closest('.topbar') || bell.closest('header');
  const barRect=bar?bar.getBoundingClientRect():r;
  const margin=12, gap=8;
  const top=Math.max(margin,barRect.bottom+gap,r.bottom+gap);
  const width=panel.offsetWidth;
  // Start below the bell, extending toward the profile and logout controls.
  const left=Math.max(margin,Math.min(r.left,innerWidth-width-margin));
  panel.style.top=`${top}px`;
  panel.style.left=`${left}px`;
  panel.style.maxHeight=`${Math.max(0,Math.min(420,innerHeight-top-margin))}px`;
 }
 function render(){const n=items.filter(x=>x.unread).length;badge.textContent=n>99?'99+':String(n);badge.hidden=!n;bell.setAttribute('aria-label',`Notifications (${n} unread)`);unread.textContent=`Unread (${n})`;all.setAttribute('aria-pressed',String(!onlyUnread));unread.setAttribute('aria-pressed',String(onlyUnread));mark.disabled=busy||!n||!csrf;list.replaceChildren();const visible=items.filter(x=>!onlyUnread||x.unread);if(!visible.length){status.textContent=loaded?(onlyUnread?'No unread notifications.':'No notifications yet.'):'Loading notifications…';return;}status.textContent='';for(const item of visible){const row=node('button',`nt-row${item.unread?' nt-new':''}`);row.type='button';const name=item.creator||'Employee';const initials=name.trim().split(/\s+/).slice(0,2).map(x=>Array.from(x)[0]||'').join('').toUpperCase();row.append(node('span','nt-avatar',initials||'U'));const text=node('span','nt-text');text.append(node('span','nt-name',name),node('span','nt-description',item.description));row.append(text);const meta=node('span','nt-meta');if(item.unread)meta.append(node('span','nt-dot'));meta.append(node('span','nt-age',['reminder','task','inventory'].includes(item.kind)?'Reminder':age(item.age_seconds)));row.append(meta);row.addEventListener('click',async()=>{if(busy)return;if(item.unread&&!(await load('read',item.id)))return;location.href=item.url;});list.append(row);}}
 async function load(action,id){if(busy)return false;busy=true;mark.disabled=true;try{const opts={credentials:'same-origin',cache:'no-store'};if(action){opts.method='POST';opts.body=new URLSearchParams({action,csrf,id:id||''});}const res=await fetch('notification_api.php',opts);const data=await res.json();if(!res.ok||data.error)throw Error(data.error||'Notifications unavailable.');items=data.items;csrf=data.csrf;loaded=true;busy=false;render();return true;}catch(e){status.textContent=e.message;return false;}finally{busy=false;mark.disabled=!csrf||!items.some(x=>x.unread);}}
 function hide(){panel.hidden=true;bell.setAttribute('aria-expanded','false');}
 bell.addEventListener('click',()=>{if(!panel.hidden){hide();return;}panel.hidden=false;bell.setAttribute('aria-expanded','true');place();load();});close.addEventListener('click',()=>{hide();bell.focus();});all.addEventListener('click',()=>{onlyUnread=false;render();});unread.addEventListener('click',()=>{onlyUnread=true;render();});mark.addEventListener('click',()=>load('read_all'));
 document.addEventListener('pointerdown',e=>{if(!panel.hidden&&!panel.contains(e.target)&&!bell.contains(e.target))hide();});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){hide();bell.focus();}});window.addEventListener('resize',()=>{if(!panel.hidden)place();});window.addEventListener('scroll',()=>{if(!panel.hidden)place();},{passive:true});load();setInterval(()=>{if(!document.hidden)load();},30000);
})();
