{{-- Styles of the fichas, the same look as the quick search of abianv2. --}}
<style>
    .fc-card { background: #fff; border: 1px solid var(--gray-200); border-radius: .75rem; box-shadow: 0 1px 2px rgb(15 23 42 / .05); overflow: hidden; }
    .fc-cabecera { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; padding: .875rem 1rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
    .fc-nombre { font-size: 1.0625rem; font-weight: 700; }
    .fc-contacto { display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; margin-top: .25rem; font-size: .875rem; color: var(--gray-600); }
    .fc-contacto a, .fc-enlace { color: var(--primary-600); }
    .fc-enlace:hover { text-decoration: underline; }
    .fc-dato { display: inline-flex; align-items: center; gap: .3rem; }
    .fc-dato svg { width: 1rem; height: 1rem; color: var(--gray-400); }
    .fc-insignias { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .375rem; }
    .fc-cuerpo { padding: .25rem 1rem .75rem; }
    .fc-part { border-left: 3px solid var(--gray-200); margin: .75rem 0 0 .25rem; padding-left: .875rem; }
    .fc-fila { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem .625rem; font-size: .875rem; }
    .fc-fila .cod { font-weight: 700; font-family: ui-monospace, monospace; font-size: .8125rem; }
    .fc-sub { margin-top: .5rem; padding: .5rem .75rem; border: 1px solid var(--gray-200); border-radius: .5rem; background: var(--gray-50); font-size: .875rem; }
    .fc-sub-tit { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem .625rem; }
    .fc-sub-tit strong { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .fc-reuniones { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .5rem; }
    .fc-reunion { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid var(--gray-200); background: #fff; border-radius: .375rem; padding: .125rem .5rem; font-size: .8125rem; }
    .fc-tenue { color: var(--gray-500); }
    .fc-vacio { padding: .75rem 0 0; font-size: .875rem; color: var(--gray-500); }
    .fc-boton { margin-left: auto; display: inline-flex; align-items: center; gap: .25rem; font-size: .8125rem; font-weight: 600; color: var(--primary-600); border: 1px solid var(--gray-300); border-radius: .5rem; padding: .125rem .5rem; background: #fff; }
    .fc-boton:hover { background: var(--gray-50); }
    .fc-boton svg { width: .875rem; height: .875rem; }
    .dark .fc-card { background: var(--gray-900); border-color: var(--gray-700); }
    .dark .fc-cabecera, .dark .fc-sub { background: var(--gray-800); border-color: var(--gray-700); }
    .dark .fc-reunion, .dark .fc-boton { background: var(--gray-900); border-color: var(--gray-700); }
    .dark .fc-part { border-color: var(--gray-700); }
    .dark .fc-contacto { color: var(--gray-300); }
</style>
