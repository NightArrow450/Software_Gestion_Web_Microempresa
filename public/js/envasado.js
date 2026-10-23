document.querySelectorAll('[data-materiales]').forEach(form => {
 let siguiente = form.querySelectorAll('[data-filas-materiales] fieldset').length;
 form.addEventListener('click', event => {
  if (event.target.closest('[data-agregar-material]')) {
   form.querySelector('[data-filas-materiales]').insertAdjacentHTML('beforeend', form.querySelector('template').innerHTML.replaceAll('__INDICE__', String(siguiente++)));
  }
  event.target.closest('[data-retirar-material]')?.closest('fieldset').remove();
 });
});

const alternativa = document.querySelector('#configuracion-envasado');
if (alternativa) {
 const actualizar = () => { document.querySelector('#version-configuracion').value = alternativa.selectedOptions[0]?.dataset.version ?? ''; };
 alternativa.addEventListener('change', actualizar); actualizar();
}

const presentacionEnvasado = document.querySelector('#presentacion-envasado');
if (alternativa && presentacionEnvasado) {
 const filtrarAlternativas = () => {
  Array.from(alternativa.options).forEach(opcion => {
   if (!opcion.value) return;
   opcion.hidden = opcion.dataset.presentacion !== presentacionEnvasado.value;
   opcion.disabled = opcion.hidden;
  });
  if (alternativa.selectedOptions[0]?.disabled) alternativa.value = '';
  alternativa.dispatchEvent(new Event('change'));
 };
 presentacionEnvasado.addEventListener('change', filtrarAlternativas); filtrarAlternativas();
}
