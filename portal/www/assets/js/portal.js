// Portal TechCorp: confirma antes de salir de un formulario con cambios.
document.addEventListener('DOMContentLoaded', () => {
  let sucio = false;
  document.querySelectorAll('form textarea, form input').forEach(el => el.addEventListener('input', () => { sucio = true; }));
  document.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => { sucio = false; }));
  window.addEventListener('beforeunload', ev => { if (sucio) { ev.preventDefault(); } });
});
