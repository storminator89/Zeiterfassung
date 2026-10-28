(() => {
    const el=id=>document.getElementById(id), token=document.querySelector('meta[name=kiosk-token]').content;
    let timer, generation=0, busyFor=null, identifyRequest=null, interactionToken=null, durable=false;
    const requestId=()=>Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
    const clock=()=>{el('clock').textContent=new Date().toLocaleTimeString('de-DE',{timeZone:'Europe/Berlin',hour:'2-digit',minute:'2-digit'});el('date').textContent=new Date().toLocaleDateString('de-DE',{timeZone:'Europe/Berlin',weekday:'long',day:'numeric',month:'long'});};clock();setInterval(clock,1000);
    async function call(path,data){const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json','X-Kiosk-CSRF':token},body:JSON.stringify(data)});const result=await r.json();if(!r.ok)throw Error(typeof result.message==='string'?result.message:'Nicht bestätigt. Bitte erneut anmelden.');return result;}
    function message(text,error=false){el('message').textContent=text;el('message').classList.toggle('error',error);}
    function clearPerson(){generation++;clearTimeout(timer);busyFor=null;identifyRequest=null;interactionToken=null;durable=false;el('name').textContent='';el('state').textContent='';el('person').hidden=true;el('login').hidden=false;el('identify').reset();el('confirm').checked=false;el('card').focus();}
    async function reset(){const selectors={identify_request_id:identifyRequest,interaction_token:interactionToken};clearPerson();const g=generation;try{await call('/reset',selectors);}catch{if(g===generation)message('Verbindung zum Gerätedienst fehlt.',true);}}
    // Request ownership exists BEFORE identify responds, so timeout/reset can cancel it.
    el('card').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();el('pin').focus();}});
    el('identify').addEventListener('submit',async e=>{
        e.preventDefault();if(busyFor===generation)return;
        const g=++generation;busyFor=g;identifyRequest=requestId();interactionToken=null;durable=false;message('Anmeldung wird geprüft …');
        const data=Object.fromEntries(new FormData(e.target));data.identify_request_id=identifyRequest;el('pin').value='';
        try{const r=await call('/identify',data);if(g!==generation)return;
            durable=r.durable_pending===true;
            if(durable&&(typeof r.interaction_token!=='string'||!/^[a-f0-9]{32}$/.test(r.interaction_token)))throw Error('Keine passende aktuelle Anmeldung bestätigt.');
            interactionToken=durable?r.interaction_token:null;el('identify').reset();el('name').textContent=r.name;el('state').textContent={absent:'Nicht angemeldet',working:'Arbeitszeit läuft',paused:'Pause läuft'}[r.state];
            const allowed={absent:['start'],working:['pause_start','end'],paused:['pause_end','end']}[r.state];el('actions').querySelectorAll('button').forEach(b=>b.hidden=!allowed.includes(b.dataset.action));el('confirm-box').hidden=r.state!=='paused';el('person').hidden=false;el('login').hidden=true;message('Bitte Aktion wählen.');timer=setTimeout(()=>{reset();message('Bitte erneut anmelden.');},10000);
        }catch(error){if(g===generation){clearPerson();message(error.message,true);}}finally{if(busyFor===g)busyFor=null;}
    });
    el('actions').addEventListener('click',async e=>{
        const b=e.target.closest('button');if(!b||busyFor===generation)return;
        if(b.dataset.action==='end'&&!el('confirm-box').hidden&&!el('confirm').checked){message('Bitte das gemeinsame Beenden von Pause und Arbeitszeit bestätigen.',true);return;}
        const g=generation, wasDurable=durable, owner=interactionToken;busyFor=g;
        const data={action:b.dataset.action,confirm_end_pause:el('confirm').checked};if(wasDurable)data.interaction_token=owner;
        try{const r=await call('/event',data);if(g!==generation)return;clearPerson();message(`${r.message} ${new Date(r.time).toLocaleTimeString('de-DE',{timeZone:'Europe/Berlin'})}`);}
        catch(error){if(g===generation){if(wasDurable)clearPerson();message(error.message,true);}}finally{if(busyFor===g)busyFor=null;}
    });
    el('cancel').addEventListener('click',()=>reset());
})();
