const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function elemento() {
    return { value: '', hidden: false, disabled: false, textContent: '', children: [], atributos: {}, eventos: {},
        setAttribute(k,v) { this.atributos[k]=v; }, removeAttribute(k) { delete this.atributos[k]; },
        addEventListener(k,v) { this.eventos[k]=v; }, replaceChildren() { this.children=[]; }, append(e) { this.children.push(e); }, dispatchEvent(e) { this.ultimoEvento=e.type; }, scrollIntoView() {} };
}
function preparar(fetch) {
    const contexto={document:{createElement:elemento},location:{origin:'http://pruebas.invalid'},AbortController,Event,URL,setTimeout,clearTimeout};
    vm.runInNewContext(fs.readFileSync('public/js/pedidos-autocompletar.js','utf8'),contexto);
    const e={input:elemento(),select:elemento(),lista:elemento(),estado:elemento(),mas:elemento()};e.lista.id='sugerencias';
    e.componente=contexto.PedidosAutocompletar.crear({...e,url:'/catalogo',tipo:'clientes',fetch});return e;
}
const respuesta=(data, pagina=1, mas=false)=>({ok:true,json:async()=>({data,current_page:pagina,next_page_url:mas?'otra':null})});
test('las sugerencias se muestran debajo y seleccionar utiliza texto seguro',async()=>{
    const e=preparar(async()=>respuesta([{id:4,nombre:'<img src=x onerror=alert(1)>'}]));
    await e.componente.buscar();assert.equal(e.lista.hidden,false);assert.equal(e.input.atributos['aria-expanded'],'true');
    assert.equal(e.lista.children[0].textContent,'<img src=x onerror=alert(1)>');
    e.lista.children[0].eventos.click();assert.equal(e.select.value,'4');assert.equal(e.select.ultimoEvento,'change');assert.equal(e.lista.hidden,true);
});
test('flechas y Enter seleccionan y Escape cierra sin enviar el formulario',async()=>{
    const e=preparar(async()=>respuesta([{id:1,nombre:'Ana'},{id:2,nombre:'José'}]));await e.componente.buscar();
    let evitados=0;for(const key of ['ArrowDown','ArrowDown','Enter'])e.input.eventos.keydown({key,preventDefault(){evitados++;}});
    assert.equal(e.select.value,'2');assert.equal(evitados,3);e.input.eventos.keydown({key:'Escape'});assert.equal(e.input.atributos['aria-expanded'],'false');
});
test('cargar más conserva la página y no duplica resultados',async()=>{
    const urls=[];const e=preparar(async u=>{urls.push(u);return urls.length===1?respuesta([{id:1,nombre:'Uno'}],1,true):respuesta([{id:1,nombre:'Uno'},{id:2,nombre:'Dos'}],2);});
    await e.componente.buscar();assert.equal(e.mas.hidden,false);await e.componente.buscar(true);assert.equal(e.lista.children.length,2);assert.equal(urls[1].searchParams.get('page'),'2');assert.equal(e.mas.hidden,true);
});
test('una respuesta anterior no reemplaza la búsqueda más reciente',async()=>{
    let liberar;let llamadas=0;const e=preparar(async()=>++llamadas===1?new Promise(r=>{liberar=r;}):respuesta([{id:2,nombre:'Actual'}]));
    const anterior=e.componente.buscar();e.input.value='actual';await e.componente.buscar();liberar(respuesta([{id:1,nombre:'Anterior'}]));await anterior;assert.equal(e.lista.children[0].textContent,'Actual');
});
test('muestra ausencia de coincidencias y errores de consulta',async()=>{
    const e=preparar(async()=>respuesta([]));await e.componente.buscar();assert.match(e.estado.textContent,/No se encontraron/);assert.equal(e.lista.hidden,true);
    const fallo=preparar(async()=>({ok:false}));await fallo.componente.buscar();assert.match(fallo.estado.textContent,/No se pudo consultar/);assert.equal(fallo.input.atributos['aria-busy'],'false');
});
test('escribir rápidamente consulta una vez y retira la selección anterior',async()=>{
    let veces=0;const e=preparar(async()=>{veces++;return respuesta([]);});e.select.value='7';e.input.value='q';e.input.eventos.input();e.input.value='quita';e.input.eventos.input();
    assert.equal(e.select.value,'');assert.equal(veces,0);await new Promise(r=>setTimeout(r,300));assert.equal(veces,1);e.componente.cancelar();
});
