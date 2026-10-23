document.querySelectorAll('[data-materiales]').forEach(form => {
 let siguiente = form.querySelectorAll('[data-filas-materiales] fieldset').length;
 form.addEventListener('click', event => {
  if (event.target.closest('[data-agregar-material]')) {
   form.querySelector('[data-filas-materiales]').insertAdjacentHTML('beforeend', form.querySelector('template').innerHTML.replaceAll('__INDICE__', String(siguiente++)));
  }
  event.target.closest('[data-retirar-material]')?.closest('fieldset').remove();
 });
});
