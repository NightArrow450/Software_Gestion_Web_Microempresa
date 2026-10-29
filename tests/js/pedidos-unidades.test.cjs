const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const contexto = {};
vm.runInNewContext(fs.readFileSync(require('node:path').resolve(__dirname, '../../public/js/pedidos-unidades.js'), 'utf8'), contexto);
const {resumir, copiar} = contexto.PedidosUnidadesUtilidades;
const lineas = () => [1,2,3].map((id,i)=>({presentacion_producto_id:id,cantidad:[5,24,24][i],precio_unitario:'2.00',cantidad_espera:0}));
test('53 unidades agrupadas y sueltas no duplican el importe',()=>{
    const resultado=resumir(lineas(),[{capacidad:24,componentes:[{sku_id:2,cantidad:24}]},{capacidad:30,componentes:[{sku_id:3,cantidad:24}]}]);
    assert.deepEqual(JSON.parse(JSON.stringify(resultado)),{unidades:53,centimos:10600,espera:0,agrupadas:48,sueltas:5,paquetes:2});
});
test('la venta suelta no crea paquetes',()=>assert.equal(resumir(lineas(),[]).paquetes,0));
test('las unidades en espera permanecen en la cantidad comercial',()=>{const l=lineas();l[1].cantidad_espera=22;const r=resumir(l,[]);assert.equal(r.espera,22);assert.equal(r.unidades,53);});
test('una agrupación puede quedar incompleta explícitamente',()=>assert.equal(resumir(lineas(),[{capacidad:30,componentes:[{sku_id:2,cantidad:5}]}]).sueltas,48));
test('no se asignan las mismas unidades dos veces',()=>assert.throws(()=>resumir(lineas(),[{capacidad:30,componentes:[{sku_id:1,cantidad:3}]},{capacidad:30,componentes:[{sku_id:1,cantidad:3}]}]),/más unidades/));
test('rechaza cantidades fraccionarias',()=>{const l=lineas();l[0].cantidad=1.5;assert.throws(()=>resumir(l,[]),/enteras/);});
test('rechaza componentes que no pertenecen al pedido',()=>assert.throws(()=>resumir(lineas(),[{capacidad:30,componentes:[{sku_id:99,cantidad:2}]}]),/SKU/));
test('rechaza un paquete sin componentes',()=>assert.throws(()=>resumir(lineas(),[{capacidad:24,componentes:[]}]),/composición/));
test('copiar un paquete permite cambiarlo sin alterar el original',()=>{const a={capacidad:12,componentes:[{sku_id:1,cantidad:5}]};const b=copiar(a);b.componentes[0].cantidad=2;assert.equal(a.componentes[0].cantidad,5);assert.equal(b.componentes[0].cantidad,2);});

test('copiar conserva composición pero no reutiliza la identidad del paquete físico',()=>{const a={identidad:'identidad-anterior',capacidad:12,componentes:[{sku_id:1,cantidad:5}]};const b=copiar(a);assert.equal(b.identidad,undefined);assert.equal(a.identidad,'identidad-anterior');assert.equal(b.capacidad,12);});

test('el tope económico se valida sin imponer múltiplos ni un máximo de diez mil unidades',()=>{assert.equal(resumir([{presentacion_producto_id:1,cantidad:10001,precio_unitario:'2.00'}],[]).unidades,10001);assert.throws(()=>resumir([{presentacion_producto_id:1,cantidad:500000,precio_unitario:'2.00'}],[]),/importe/);});
