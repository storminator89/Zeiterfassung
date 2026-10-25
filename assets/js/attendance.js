(() => {
    const actions=document.getElementById('actions'), message=document.getElementById('message');
    const labels={absent:'Nicht angemeldet',working:'Arbeitszeit läuft',paused:'Pause läuft'};
    let pending=null, busy=false;
    function update(state) {
        actions.dataset.state=state;
        const allowed={absent:['start'],working:['pause_start','end'],paused:['pause_end','end']}[state];
        actions.querySelectorAll('button').forEach(b=>{b.hidden=!allowed.includes(b.dataset.action);b.disabled=false;});
        document.getElementById('status').textContent=labels[state];
    }
    update(actions.dataset.state);
    actions.addEventListener('click',async e=>{
        const button=e.target.closest('button'); if(!button||busy)return;
        if(pending && pending.action!==button.dataset.action){message.textContent='Unbestätigte Buchung zuerst mit derselben Aktion erneut versuchen.';return;}
        const confirmPause=button.dataset.action==='end'&&actions.dataset.state==='paused';
        if(confirmPause&&!pending&&!confirm('Pause und Arbeitszeit gemeinsam beenden?'))return;
        pending=pending||{event_id:crypto.randomUUID(),action:button.dataset.action,confirm_end_pause:confirmPause?'1':'0'};
        busy=true; actions.querySelectorAll('button').forEach(b=>b.disabled=true);
        try{
            const response=await fetch('attendance.php',{method:'POST',body:new URLSearchParams({...pending,csrf_token:document.querySelector('meta[name=csrf-token]').content})});
            const data=await response.json();
            if(!response.ok){if(response.status<500)pending=null;throw Error(data.message);}
            pending=null;update(data.state);message.textContent=`${data.message} ${data.local_time}`;
        }catch(error){message.textContent=error.message||'Nicht bestätigt. Dieselbe Aktion erneut versuchen.';}
        finally{busy=false;actions.querySelectorAll('button').forEach(b=>b.disabled=false);}
    });
})();
