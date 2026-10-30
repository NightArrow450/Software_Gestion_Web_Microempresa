const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/js/pedidos-unidades.js'), 'utf8');
const contexto = {};
vm.runInNewContext(script, contexto);
const unidades = contexto.PedidosUnidadesUtilidades;
const lineas = () => [{presentacion_producto_id:10, cantidad:24, cantidad_espera:0, precio_unitario:'2.00', nombre:'Lavanda', sku:'LAV'}, {presentacion_producto_id:20, cantidad:24, cantidad_espera:0, precio_unitario:'2.00', nombre:'Limón', sku:'LIM'}];
const paquete = () => ({capacidad:12, componentes:[{sku_id:10,cantidad:12}]});

test('copiar una agrupación conserva cantidades y permite editar cada paquete independientemente', () => {
    const original = paquete();
    const copia = unidades.copiar(original);
    copia.componentes[0].cantidad = 5;
    assert.equal(original.componentes[0].cantidad, 12);
    assert.equal(unidades.resumir(lineas(), [copia]).agrupadas, 5);
});

test('las agrupaciones opcionales no inventan aromas ni exigen llenar su capacidad', () => {
    assert.equal(unidades.resumir(lineas(), []).sueltas, 48);
    const grupo = paquete(); grupo.componentes[0].cantidad = 11;
    assert.equal(unidades.resumir(lineas(), [grupo]).agrupadas, 11);
    grupo.componentes[0].cantidad = 13;
    assert.throws(() => unidades.resumir(lineas(), [grupo]), /capacidad/);
});

test('rechaza SKU ajenos, repetidos y cantidades fraccionarias sin repartir unidades', () => {
    for (const componentes of [[{sku_id:99,cantidad:12}], [{sku_id:10,cantidad:6},{sku_id:10,cantidad:6}], [{sku_id:10,cantidad:11.5}]]) {
        assert.throws(() => unidades.resumir(lineas(), [{capacidad:12,componentes}]));
    }
});

function descendientes(nodo) { return nodo.children.flatMap(hijo => [hijo,...descendientes(hijo)]); }
function prepararEditor(grupos) {
    function elemento(tag='div') {
        return {tag, children:[], eventos:{}, dataset:{}, atributos:{}, value:'', textContent:'',
            append(...hijos) { this.children.push(...hijos); }, replaceChildren() {this.children=[];},
            addEventListener(nombre,accion) {this.eventos[nombre]=accion;}, setAttribute(nombre,valor) {this.atributos[nombre]=valor;}, focus() {},
            get options() {return this.children;},
            querySelectorAll(selector) {return descendientes(this).filter(n => selector === '[data-total-grupo]' ? n.dataset.totalGrupo !== undefined : selector.split(',').map(x=>x.trim()).includes(n.tag));}
        };
    }
    const form=elemento('form'), campos=new Map(); form.dataset.catalogo='/pedidos-unidades/catalogo';
    form.querySelector=selector=>{if(!campos.has(selector)){const nodo=elemento();campos.set(selector,nodo);form.append(nodo);}return campos.get(selector);};
    const document={createElement:elemento,querySelector:s=>s==='[data-pedido-unidades]'?form:null,getElementById:()=>({textContent:JSON.stringify({lineas:lineas(),agrupaciones:grupos})})};
    const ctx={document,URL,location:{origin:'http://localhost'},fetch:async()=>({ok:true,json:async()=>({data:[],current_page:1,next_page_url:null})}),setTimeout,clearTimeout,confirm:()=>true};
    vm.runInNewContext(script,ctx);
    const raiz=campos.get('[data-grupos]');
    return {raiz,campos,leer:()=>JSON.parse(campos.get('[data-agrupaciones-json]').value),nodos:()=>descendientes(raiz)};
}
function boton(e,texto) {return e.nodos().find(n=>n.tag==='button'&&n.textContent===texto);}
function cantidad(e) {return e.nodos().find(n=>n.atributos['aria-label']==='Unidades dentro del paquete');}

test('el editor copia al paquete elegido y editar la copia no modifica el original', () => {
    const e=prepararEditor([paquete()]);
    boton(e,'Copiar este paquete').eventos.click();
    const campo=cantidad(e);campo.value='11';campo.eventos.input();
    const grupos=e.leer();
    assert.equal(grupos[0].componentes[0].cantidad,12);
    assert.equal(grupos[1].componentes[0].cantidad,11);
    assert.equal(grupos.length,2);
});

test('añadir un paquete conserva lo definido y no elige SKU ni cantidades automáticamente', () => {
    const e=prepararEditor([paquete()]);
    e.campos.get('[data-agregar-grupo]').eventos.click();
    const grupos=e.leer();
    assert.deepEqual(grupos[0],paquete());
    assert.deepEqual(grupos[1].componentes,[]);
    assert.match(e.campos.get('[data-mensaje]').textContent,/composición/);
});

test('navegar a un número inválido no pierde ni crea agrupaciones', () => {
    const e=prepararEditor([paquete()]);
    const numero=e.nodos().find(n=>n.tag==='input'&&n.max==='1');
    for (const valor of ['', '0', '1.5', '2', 'texto']) {
        numero.value=valor;numero.eventos.change();
        assert.equal(numero.value,'1');
        assert.deepEqual(e.leer(),[paquete()]);
    }
});

test('el editor conserva aroma y SKU visibles al cambiar selección y cantidad', () => {
    const e=prepararEditor([paquete()]);
    let selector=e.nodos().find(n=>n.tag==='select');
    assert.equal(selector.options.find(n=>Number(n.value)===10).textContent,'Lavanda · LAV');
    selector.value='20';selector.eventos.change();
    selector=e.nodos().find(n=>n.tag==='select');
    const n=cantidad(e);n.value='5';n.eventos.input();
    assert.equal(selector.options.find(n=>Number(n.value)===20).textContent,'Limón · LIM');
    assert.equal(e.leer()[0].componentes[0].sku_id,20);
    assert.equal(e.leer()[0].componentes[0].cantidad,5);
});
