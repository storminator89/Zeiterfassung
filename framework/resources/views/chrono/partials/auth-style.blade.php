{{-- Fixed local auth-card CSS: no script, remote stylesheet, font, import or URL. --}}
<style>
    .chrono-auth, .chrono-auth *, .chrono-auth *::before, .chrono-auth *::after {
        box-sizing: border-box;
    }
    .chrono-auth {
        margin: 0;
        color: #1f2937;
        font-family: 'Poppins', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 1rem;
        line-height: 1.5;
        -webkit-text-size-adjust: 100%;
    }
    .chrono-auth h2, .chrono-auth p { margin: 0; }
    .chrono-auth fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
    .chrono-auth button, .chrono-auth input { font: inherit; letter-spacing: inherit; }
    .chrono-auth img, .chrono-auth svg { display: block; vertical-align: middle; }
    .chrono-auth img { max-width: 100%; }
    .chrono-auth .card {
        position: relative;
        display: flex;
        min-width: 0;
        flex-direction: column;
        border-radius: 1rem;
        overflow-wrap: anywhere;
    }
    .chrono-auth .card-body { display: flex; flex: 1 1 auto; flex-direction: column; gap: .5rem; padding: 2rem; }
    .chrono-auth .card-title { display: flex; align-items: center; gap: .5rem; font-weight: 600; }
    .chrono-auth .form-control { display: flex; flex-direction: column; }
    .chrono-auth .label { display: flex; align-items: center; justify-content: space-between; padding: .5rem .25rem; }
    .chrono-auth .label-text { font-size: .875rem; line-height: 1.25rem; }
    .chrono-auth .input {
        display: inline-flex;
        min-width: 0;
        height: 3rem;
        padding: 0 1rem;
        border: 1px solid #d1d5db;
        border-radius: .5rem;
        color: #1f2937;
        background-color: #fff;
        font-size: 1rem;
        line-height: 1.5rem;
    }
    .chrono-auth .input:focus { border-color: #2563eb; outline: 2px solid #2563eb; outline-offset: 2px; }
    .chrono-auth .input::placeholder { color: #9ca3af; opacity: 1; }
    .chrono-auth .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 3rem;
        height: 3rem;
        padding: 0 1rem;
        border: 1px solid #570df8;
        border-radius: .5rem;
        cursor: pointer;
        user-select: none;
        font-size: .875rem;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        text-transform: uppercase;
    }
    .chrono-auth .btn-primary { color: #fff; background-color: #2563eb; }
    .chrono-auth .btn:focus-visible, .chrono-auth .link:focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
    .chrono-auth .btn:disabled, .chrono-auth fieldset:disabled .btn { opacity: .5; cursor: not-allowed; }
    .chrono-auth .link { color: #570df8; cursor: pointer; text-decoration: underline; text-underline-offset: 2px; }
    .chrono-auth .alert { display: grid; align-items: center; gap: 1rem; width: 100%; padding: 1rem; border: 1px solid transparent; border-radius: 1rem; }
    .chrono-auth .alert > div { display: flex; align-items: center; gap: .5rem; }
    .chrono-auth .alert-error { color: #470000; background-color: #f87272; }
    .chrono-auth .alert-success { color: #003320; background-color: #36d399; }
    .chrono-auth.flex, .chrono-auth .flex { display: flex; }
    .chrono-auth .flex-col { flex-direction: column; }
    .chrono-auth .flex-shrink-0 { flex-shrink: 0; }
    .chrono-auth.items-center, .chrono-auth .items-center { align-items: center; }
    .chrono-auth.justify-center { justify-content: center; }
    .chrono-auth.min-h-screen { min-height: 100vh; min-height: 100svh; }
    .chrono-auth.p-4 { padding: 1rem; }
    .chrono-auth .pb-6 { padding-bottom: 1.5rem; }
    .chrono-auth .px-4 { padding-left: 1rem; padding-right: 1rem; }
    .chrono-auth .py-2 { padding-top: .5rem; padding-bottom: .5rem; }
    .chrono-auth .mb-2 { margin-bottom: .5rem; }
    .chrono-auth .mb-4 { margin-bottom: 1rem; }
    .chrono-auth .mb-6 { margin-bottom: 1.5rem; }
    .chrono-auth .mt-4 { margin-top: 1rem; }
    .chrono-auth .mt-6 { margin-top: 1.5rem; }
    .chrono-auth .space-y-4 > :not([hidden]) ~ :not([hidden]) { margin-top: 1rem; }
    .chrono-auth .w-full { width: 100%; }
    .chrono-auth .max-w-md { max-width: 28rem; }
    .chrono-auth .w-20 { width: 5rem; }
    .chrono-auth .h-20 { height: 5rem; }
    .chrono-auth .w-6 { width: 1.5rem; }
    .chrono-auth .h-6 { height: 1.5rem; }
    .chrono-auth .text-center { text-align: center; }
    .chrono-auth .text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .chrono-auth .text-sm { font-size: .875rem; line-height: 1.25rem; }
    .chrono-auth .text-xs { font-size: .75rem; line-height: 1rem; }
    .chrono-auth .font-medium { font-weight: 500; }
    .chrono-auth .font-semibold { font-weight: 600; }
    .chrono-auth .text-white { color: #fff; }
    .chrono-auth .text-gray-500 { color: #6b7280; }
    .chrono-auth .text-gray-600 { color: #4b5563; }
    .chrono-auth .text-gray-700 { color: #374151; }
    .chrono-auth .text-gray-800 { color: #1f2937; }
    .chrono-auth .stroke-current { stroke: currentColor; }
    .chrono-auth .bg-white { background-color: #fff; }
    .chrono-auth .bg-gray-50 { background-color: #f9fafb; }
    .chrono-auth .focus\:bg-white:focus { background-color: #fff; }
    .chrono-auth.bg-gradient-to-r { background-image: linear-gradient(to right, #dbeafe, #eff6ff); }
    .chrono-auth .bg-gradient-to-r { background-image: linear-gradient(to right, #3b82f6, #2563eb); }
    .chrono-auth .hover\:from-blue-600:hover { background-image: linear-gradient(to right, #2563eb, #1d4ed8); }
    .chrono-auth .rounded-lg { border-radius: .5rem; }
    .chrono-auth .shadow-2xl { box-shadow: 0 25px 50px -12px rgb(0 0 0 / .25); }
    .chrono-auth .shadow-lg, .chrono-auth .hover\:shadow-lg:hover { box-shadow: 0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1); }
    .chrono-auth .transition, .chrono-auth .transition-colors { transition: color .3s, background-color .3s, border-color .3s, box-shadow .3s, transform .3s; }
    .chrono-auth .ease-in-out { transition-timing-function: cubic-bezier(.4, 0, .2, 1); }
    .chrono-auth .hover\:-translate-y-1:hover { transform: translateY(-.25rem); }
    .chrono-auth .hover\:underline:hover { text-decoration: underline; }
    @media (prefers-reduced-motion: reduce) {
        .chrono-auth .transition, .chrono-auth .transition-colors { transition: none; }
        .chrono-auth .hover\:-translate-y-1:hover { transform: none; }
    }
</style>
