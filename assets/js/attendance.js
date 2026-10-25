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
    async function refresh() {
        const response=await fetch('attendance.php?format=status',{cache:'no-store'});
        if(!response.ok) throw Error('Anzeige konnte nicht aktualisiert werden. Bitte Seite neu laden.');
        const data=await response.json();update(data.state);
        const body=document.getElementById('recent-records');body.replaceChildren();
        for(const record of data.records) {
            const row=document.createElement('tr');
            const values=[record.absence_kind||'Arbeitszeit',record.absence_kind?`${record.startzeit.slice(0,10)} · ${record.planned_minutes} Sollminuten`:record.startzeit,record.absence_kind?'Gutschrift':record.endzeit||'Läuft',record.pause];
            for(const value of values) { const cell=document.createElement('td');cell.textContent=value??'';row.append(cell); }
            const cell=document.createElement('td'), link=document.createElement('a');link.href=`adjustments.php?record_id=${Number(record.id)}`;link.textContent='Beantragen';cell.append(link);row.append(cell);body.append(row);
        }
    }
    const absenceForm=document.getElementById('absence-form');
    absenceForm.addEventListener('submit',async event=>{
        event.preventDefault();
        const button=absenceForm.querySelector('button'), status=document.getElementById('absence-message');
        button.disabled=true;
        try {
            const response=await fetch('save.php',{method:'POST',body:new FormData(absenceForm)});
            const data=await response.json();status.textContent=data.message;
            if(response.ok) { absenceForm.reset();try{await refresh();}catch(error){status.textContent+=` ${error.message}`;} }
        } catch(error) { status.textContent='Keine Bestätigung erhalten. Seite neu laden und vorhandene Buchungen prüfen.'; }
        finally { button.disabled=false; }
    });
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
            if(!response.ok){if(response.status<500){pending=null;try{await refresh();}catch(ignored){}}throw Error(data.message);}
            pending=null;update(data.state);message.textContent=`${data.message} ${data.local_time}`;
            try{await refresh();}catch(error){message.textContent+=` ${error.message}`;}
        }catch(error){message.textContent=error.message||'Nicht bestätigt. Dieselbe Aktion erneut versuchen.';}
        finally{busy=false;actions.querySelectorAll('button').forEach(b=>b.disabled=false);}
    });
})();
