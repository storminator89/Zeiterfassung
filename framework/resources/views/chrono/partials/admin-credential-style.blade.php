{{-- Bounded local equivalents for the existing Admin card/layout, no URLs/imports/fonts/scripts. --}}
<style>
.chrono-admin-credential { --b1:0 0% 100%;--b2:210 20% 96%;--b3:214 16% 91%;--bc:215 28% 17%;--p:258 90% 56%;--pf:262 83% 50%;--pc:0 0% 100%;--su:158 64% 52%;--suc:166 100% 10%;--wa:43 96% 56%;--wac:30 90% 12%;--er:0 91% 71%;--erc:0 100% 14%;--n:219 14% 28%;--nc:0 0% 100%;margin:0;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
[data-theme="dark"] .chrono-admin-credential { --b1:220 18% 20%;--b2:220 17% 17%;--b3:220 17% 14%;--bc:220 13% 91%;--p:262 83% 70%;--pf:262 83% 63%;--pc:262 70% 12%;background:linear-gradient(135deg,#1a202c,#2d3748,#4a5568); }
.chrono-admin-credential,.chrono-admin-credential *,.chrono-admin-credential *::before,.chrono-admin-credential *::after { box-sizing:border-box; }
.chrono-admin-credential button,.chrono-admin-credential input,.chrono-admin-credential select { font:inherit; }
.chrono-admin-credential fieldset { min-width:0;margin:0;padding:0;border:0; }
.chrono-admin-credential .card { display:flex;flex-direction:column;min-width:0;border-radius:1rem;background:hsl(var(--b1));overflow-wrap:anywhere; }
.chrono-admin-credential .card-body { display:flex;flex-direction:column;gap:.5rem;padding:1.5rem; }
.chrono-admin-credential .card-title { display:flex;align-items:center;gap:.5rem;font-weight:600; }
.chrono-admin-credential .form-control { display:flex;flex-direction:column; }
.chrono-admin-credential .label { display:flex;align-items:center;justify-content:space-between;padding:.5rem .25rem; }
.chrono-admin-credential .label-text { font-size:.875rem; }
.chrono-admin-credential .input,.chrono-admin-credential .select { min-width:0;width:100%;height:3rem;padding:0 1rem;border:1px solid hsl(var(--b3));border-radius:.5rem;background:hsl(var(--b1));color:hsl(var(--bc)); }
.chrono-admin-credential input[readonly] { background:hsl(var(--b2)); }
.chrono-admin-credential .input:focus,.chrono-admin-credential .select:focus { outline:2px solid hsl(var(--p));outline-offset:2px; }
.chrono-admin-credential .btn { display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:2.5rem;padding:.5rem 1rem;border:1px solid hsl(var(--b3));border-radius:.5rem;background:hsl(var(--b2));color:hsl(var(--bc));cursor:pointer; }
.chrono-admin-credential .btn-primary { background:hsl(var(--p));border-color:hsl(var(--p));color:hsl(var(--pc)); }
.chrono-admin-credential .btn-ghost { background:transparent; }
.chrono-admin-credential .btn-sm { min-height:2rem;padding:.25rem .5rem; }
.chrono-admin-credential button:disabled,.chrono-admin-credential fieldset:disabled { opacity:.6;cursor:not-allowed; }
.chrono-admin-credential :focus-visible { outline:2px solid hsl(var(--p));outline-offset:3px; }
.chrono-admin-credential .alert { display:flex;align-items:center;gap:1rem;padding:1rem;border:1px solid hsl(var(--b3));border-radius:.75rem;background:hsl(var(--b2)); }
.chrono-admin-credential .alert-info { border-left:4px solid hsl(var(--p)); }
.chrono-admin-credential .alert-success { background:hsl(var(--su));color:hsl(var(--suc)); }
.chrono-admin-credential .alert-warning { background:hsl(var(--wa));color:hsl(var(--wac)); }
.chrono-admin-credential .alert-error { background:hsl(var(--er));color:hsl(var(--erc)); }
.chrono-admin-credential .table { width:100%;border-collapse:collapse; }
.chrono-admin-credential .table th { text-align:left; }
.chrono-admin-credential .table tr { border-bottom:1px solid hsl(var(--b3)); }
.chrono-admin-credential .modal { max-width:calc(100vw - 2rem);padding:0;border:0;background:transparent;color:hsl(var(--bc)); }
.chrono-admin-credential .modal::backdrop { background:rgb(0 0 0 / .45); }
.chrono-admin-credential .modal-box { width:min(40rem,calc(100vw - 2rem));padding:1.5rem;border-radius:1rem;background:hsl(var(--b1)); }
.chrono-admin-credential .modal-action { display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem; }
.chrono-admin-credential .hidden { display:none; }
.chrono-admin-credential [hidden] { display:none!important; }
.chrono-admin-credential .inline { display:inline; }
.chrono-admin-credential .flex-grow { flex-grow:1; }
.chrono-admin-credential .space-x-2 > * + * { margin-left:.5rem; }
.chrono-admin-credential .btn-secondary { background:hsl(var(--n));color:hsl(var(--nc)); }
.chrono-admin-credential .btn-group,.chrono-admin-credential .input-group { display:flex; }
.chrono-admin-credential .table th,.chrono-admin-credential .table td { padding:.75rem; }
.chrono-admin-credential .space-y-4 > * + * { margin-top:1rem; }
.chrono-admin-credential .mb-8 { margin-bottom:2rem; }
.chrono-admin-credential .fas { font-style:normal; }
.chrono-admin-credential .fas::before { content:'•'; }
.chrono-admin-credential .fa-plus::before { content:'+'; }
.chrono-admin-credential .fa-sync::before { content:'↻'; }
.chrono-admin-credential .fa-trash-alt::before { content:'🗑'; }
.chrono-admin-credential .fa-edit::before { content:'✎'; }
.chrono-admin-credential .fa-check-circle::before { content:'✓'; }
.chrono-admin-credential .fa-exclamation-circle::before { content:'!'; }
@media(max-width:640px) { .chrono-admin-credential .card-body { padding:1rem; }.chrono-admin-credential .container { padding:1rem; }.chrono-admin-credential .modal-action { flex-wrap:wrap; } }
</style>
