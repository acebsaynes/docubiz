// Local-only emoji picker. No network calls or OS keyboard launch.
window.docubizEmojiKeyboard = function(input) {
 if (!input) return;
 const host=input.closest('.comm-message, .chat-input-area') || input.parentElement;
 const existing=host.querySelector('.docubiz-emoji-picker');
 if(existing){existing.remove();input.focus();return;}
 const panel=document.createElement('div');panel.className='docubiz-emoji-picker';
 panel.setAttribute('aria-label','Sample emojis');
 const buttons=document.createElement('div');buttons.className='docubiz-emoji-buttons';
 for(const emoji of ['😊','👍','🎉','❤️','✅','🙏','😂','🙌','🐱','✨','🔥','💯']){
  const b=document.createElement('button');b.type='button';b.textContent=emoji;b.setAttribute('aria-label','Insert '+emoji);
  b.addEventListener('mousedown',e=>e.preventDefault());
  b.addEventListener('click',()=>{const start=input.selectionStart??input.value.length,end=input.selectionEnd??start;
   if(input.maxLength>=0&&input.value.length-(end-start)+emoji.length>input.maxLength)return;
   input.setRangeText(emoji,start,end,'end');input.focus();input.dispatchEvent(new Event('input',{bubbles:true}));});buttons.append(b);
 }
 const tip=document.createElement('p');tip.textContent='Windows: Win + . (period) · macOS: Control + Command + Space. Search for more emojis using your device keyboard.';
 panel.append(buttons,tip);host.append(panel);input.focus();
};
