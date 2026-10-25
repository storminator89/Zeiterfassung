(() => {
    const el=id=>document.getElementById(id), token=document.querySelector('meta[name=kiosk-token]').content;
    let timer, busy=false, generation=0;
    const clock=()=>{el('clock').textContent=new Date().toLocaleTimeString('de-DE',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit'});el('date').textContent=new Date().toLocaleDateString('de-DE',{timeZone:'Europe/Berlin',weekday:'long',day:'numeric',month:'long'});};clock();setInterval(clock,1000);
    async function call(path,data){const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json','X-Kiosk-CSRF':token},body:JSON.stringify(data)});const result=await r.json();if(!r.ok)throw Error(result.message||'Nicht bestätigt. Bitte erneut anmelden.');return result;}
    function message(text,error=false){el('message').textContent=text;el('message').classList.toggle('error',error);}
    function clearPerson(){generation++;clearTimeout(timer);el('name').textContent='';el('state').textContent='';el('person').hidden=true;el('login').hidden=false;el('identify').reset();el('confirm').checked=false;el('card').focus();}
    async function reset(){clearPerson();try{await call('/reset',{});}catch{message('Verbindung zum Gerätedienst fehlt.',true);}}
    // Keyboard-wedge NFC readers end with Enter. Move to PIN instead of submitting an empty PIN.
    el('card').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();el('pin').focus();}});
    el('identify').addEventListener('submit',async e=>{
        e.preventDefault();if(busy)return;busy=true;const thisGeneration=++generation;message('Anmeldung wird geprüft …');
        const data=Object.fromEntries(new FormData(e.target));el('pin').value='';
        try{const r=await call('/identify',data);if(thisGeneration!==generation)return;el('identify').reset();el('name').textContent=r.name;el('state').textContent={absent:'Nicht angemeldet',working:'Arbeitszeit läuft',paused:'Pause läuft'}[r.state];
            const allowed={absent:['start'],working:['pause_start','end'],paused:['pause_end','end']}[r.state];el('actions').querySelectorAll('button').forEach(b=>b.hidden=!allowed.includes(b.dataset.action));el('confirm-box').hidden=r.state!=='paused';el('person').hidden=false;el('login').hidden=true;message('Bitte Aktion wählen.');timer=setTimeout(()=>{reset();message('Bitte erneut anmelden.');},10000);
        }catch(error){clearPerson();message(error.message,true);}finally{busy=false;}
    });
    el('actions').addEventListener('click',async e=>{
        const b=e.target.closest('button');if(!b||busy)return;
        if(b.dataset.action==='end'&&!el('confirm-box').hidden&&!el('confirm').checked){message('Bitte das gemeinsame Beenden von Pause und Arbeitszeit bestätigen.',true);return;}
        busy=true;const g=generation;
        try{const r=await call('/event',{action:b.dataset.action,confirm_end_pause:el('confirm').checked});clearPerson();message(`${r.message} ${new Date(r.time).toLocaleTimeString('de-DE',{timeZone:'Europe/Berlin'})}`);}
        catch(error){if(g===generation)message(error.message,true);}finally{busy=false;}
    });
    el('cancel').addEventListener('click',()=>reset());
})();
