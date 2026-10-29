(function (global) {
    'use strict';
    function resumir(lineas, grupos) {
        let unidades = 0, centimos = 0, espera = 0, agrupadas = 0;
        const disponibles = new Map(), asignadas = new Map();
        for (const l of lineas) {
            const n = Number(l.cantidad), e = Number(l.cantidad_espera || 0), precio = Number(l.precio_unitario);
            if (!Number.isSafeInteger(n) || n < 0 || !Number.isSafeInteger(e) || e < 0 || e > n) throw new Error('Indica unidades enteras y una espera que no supere lo solicitado.');
            if (!Number.isFinite(precio) || precio <= 0) throw new Error('Falta un precio unitario válido.');
            unidades += n; espera += e; centimos += n * Math.round(precio * 100);
            disponibles.set(Number(l.presentacion_producto_id), n);
        }
        for (const g of grupos) {
            if (!Number.isSafeInteger(Number(g.capacidad)) || Number(g.capacidad) < 1 || !g.componentes.length) throw new Error('Cada paquete necesita capacidad y composición.');
            let total = 0; const vistos = new Set();
            for (const c of g.componentes) {
                const id = Number(c.sku_id), n = Number(c.cantidad);
                if (!Number.isSafeInteger(n) || n < 1 || vistos.has(id) || !disponibles.has(id)) throw new Error('Revisa los SKU y las unidades de cada paquete.');
                vistos.add(id); total += n;
                asignadas.set(id, (asignadas.get(id) || 0) + n);
                if (asignadas.get(id) > disponibles.get(id)) throw new Error('Los paquetes asignan más unidades que las vendidas.');
            }
            if (total > Number(g.capacidad)) throw new Error('Un paquete supera su capacidad declarada.');
            agrupadas += total;
        }
        return {unidades, centimos, espera, agrupadas, sueltas: unidades - agrupadas, paquetes: grupos.length};
    }
    function copiar(grupo) { return JSON.parse(JSON.stringify(grupo)); }
    global.PedidosUnidadesUtilidades = {resumir, copiar};
    if (typeof module !== 'undefined' && module.exports) module.exports = {resumir, copiar};
    if (typeof document === 'undefined') return;
    const form = document.querySelector('[data-pedido-unidades]'); if (!form) return;
    const inicial = JSON.parse(document.getElementById('pedido-unidades-inicial').textContent);
    const lineas = (inicial.lineas || []).map(l => ({...l, cantidad:Number(l.cantidad), cantidad_espera:Number(l.cantidad_espera || 0)}));
    let grupos = Array.isArray(inicial.agrupaciones) ? inicial.agrupaciones.map(g => ({capacidad:g.capacidad, componentes:g.componentes.map(c => ({sku_id:Number(c.sku_id),cantidad:Number(c.cantidad)}))})) : [];
    let skus = [], paginas = {}, ultimaBusqueda = {};
    const buscar = s => form.querySelector(s), mensaje = buscar('[data-mensaje]');
    function opcion(select, texto, valor) { const o=document.createElement('option'); o.textContent=texto; o.value=valor; select.append(o); return o; }
    function resumen() {
        buscar('[data-agrupaciones-json]').value=JSON.stringify(grupos);
        try { const r=resumir(lineas,grupos); buscar('[data-resumen]').textContent=`${r.unidades} unidades físicas · ${r.sueltas} sueltas · ${r.agrupadas} agrupadas · ${r.paquetes} paquetes · ${r.espera} en espera · Total S/ ${(r.centimos/100).toFixed(2)}`; mensaje.textContent=''; return true; }
        catch(e) {mensaje.textContent=e.message; return false;}
    }
    function renderLineas() {
        buscar('[data-lineas]').replaceChildren();
        lineas.forEach((l,i) => {
            const card=document.createElement('div');card.className='unidades-linea';
            const titulo=document.createElement('strong');titulo.textContent=l.nombre || l.sku || `SKU ${l.presentacion_producto_id}`;card.append(titulo);
            const campos=document.createElement('div');campos.className='unidades-campos';card.append(campos);
            for (const key of ['id','presentacion_producto_id','precio_unitario','nombre','sku']) {if(l[key]) {const inp=document.createElement('input');inp.type='hidden';inp.name=`detalles[${i}][${key}]`;inp.value=l[key];card.append(inp);}}
            for (const [key,texto] of [['cantidad','Unidades solicitadas'],['cantidad_espera','Unidades en espera']]) {
                const label=document.createElement('label');label.textContent=texto;
                const input=document.createElement('input');input.className='form-control';input.name=`detalles[${i}][${key}]`;input.type='number';input.min='0';input.step='1';input.value=l[key] || 0;input.required=true;
                input.addEventListener('input',()=>{l[key]=Number(input.value);resumen();});label.append(input);campos.append(label);
            }
            const precio=document.createElement('p');precio.textContent=`Precio unitario histórico S/ ${Number(l.precio_unitario).toFixed(2)}`;campos.append(precio);
            const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-secondary';quitar.textContent='Retirar línea';
            quitar.addEventListener('click',()=>{if(l.id){l.cantidad=0;l.cantidad_espera=0;}else lineas.splice(i,1);renderLineas();renderGrupos();resumen();});campos.append(quitar);
            buscar('[data-lineas]').append(card);
        });
        resumen();
    }
    function renderGrupos() {
        buscar('[data-grupos]').replaceChildren();
        grupos.forEach((g,i)=>{
            const card=document.createElement('div');card.className='unidades-grupo';const titulo=document.createElement('h3');titulo.textContent=`Paquete ${i+1}`;card.append(titulo);
            const capacidad=document.createElement('label');capacidad.textContent='Capacidad máxima del paquete';const inp=document.createElement('input');inp.className='form-control';inp.type='number';inp.min='1';inp.step='1';inp.value=g.capacidad;inp.addEventListener('input',()=>{g.capacidad=Number(inp.value);resumen();});capacidad.append(inp);card.append(capacidad);
            g.componentes.forEach((c,j)=>{
                const fila=document.createElement('div');fila.className='unidades-campos';const select=document.createElement('select');select.className='form-control';select.setAttribute('aria-label','SKU del paquete');opcion(select,'Selecciona un SKU','');
                lineas.filter(l=>l.cantidad>0).forEach(l=>opcion(select,l.nombre || l.sku || `SKU ${l.presentacion_producto_id}`,l.presentacion_producto_id));select.value=c.sku_id || '';select.addEventListener('change',()=>{c.sku_id=Number(select.value);resumen();});fila.append(select);
                const n=document.createElement('input');n.type='number';n.min='1';n.step='1';n.className='form-control';n.value=c.cantidad;n.setAttribute('aria-label','Unidades dentro del paquete');n.addEventListener('input',()=>{c.cantidad=Number(n.value);resumen();});fila.append(n);
                const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-secondary';quitar.textContent='Quitar componente';quitar.addEventListener('click',()=>{g.componentes.splice(j,1);renderGrupos();resumen();});fila.append(quitar);card.append(fila);
            });
            const total=document.createElement('p');total.textContent=`Contenido declarado: ${g.componentes.reduce((s,c)=>s+Number(c.cantidad),0)} unidades. Si no llena la capacidad, el paquete queda expresamente incompleto.`;card.append(total);
            for(const [texto,accion] of [['Añadir variante al paquete',()=>g.componentes.push({sku_id:0,cantidad:1})],['Copiar este paquete',()=>grupos.push(copiar(g))],['Eliminar paquete',()=>grupos.splice(i,1)]]) {
                const b=document.createElement('button');b.type='button';b.className='btn btn-secondary';b.textContent=texto;b.addEventListener('click',()=>{accion();renderGrupos();resumen();});card.append(b);
            }
            buscar('[data-grupos]').append(card);
        });
    }
    async function consultar(tipo,mas=false) {
        const q=buscar(tipo==='clientes'?'[data-buscar-cliente]':'[data-buscar-producto]').value;
        const llave=tipo+'|'+q+'|'+buscar('[data-productos]').value; ultimaBusqueda[tipo]=llave;
        const url=new URL(form.dataset.catalogo,location.origin);url.searchParams.set('tipo',tipo);url.searchParams.set('q',tipo==='skus'?'':q);url.searchParams.set('page',mas?(paginas[tipo] || 2):1);
        if(tipo==='skus') url.searchParams.set('producto_id',buscar('[data-productos]').value);
        try {
            const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok) throw new Error('No se pudo consultar el catálogo. Revisa tu sesión y vuelve a intentar.');
            const data=await response.json();if(ultimaBusqueda[tipo]!==llave) return;
            const select=buscar(tipo==='clientes'?'[data-clientes]':'[data-productos]');
            if(tipo==='skus') {skus=mas?skus.concat(data.data):data.data;renderVariantes();}
            else {if(!mas){const selected=select.value;const actual=Array.from(select.options).find(o=>o.value===selected);select.replaceChildren();opcion(select,tipo==='clientes'?'Selecciona un cliente activo':'Selecciona un producto','');if(actual && actual.value) select.append(actual);}data.data.forEach(r=>{if(!Array.from(select.options).some(o=>o.value===String(r.id))) opcion(select,r.nombre,r.id);});}
            paginas[tipo]=data.current_page+1;buscar(`[data-mas-${tipo}]`).hidden=!data.next_page_url;
        } catch(e) {mensaje.textContent=e.message;}
    }
    function renderVariantes() {
        const vs=buscar('[data-variantes]'), previo=vs.value;vs.replaceChildren();const variantes=new Map(skus.map(s=>[String(s.variante_id || ''),s.variante || 'Sin variante']));variantes.forEach((v,k)=>opcion(vs,v,k));if(variantes.has(previo))vs.value=previo;
        buscar('[data-campo-variante]').hidden=!skus.some(s=>s.variante_id);renderSkus();
    }
    function renderSkus(){const s=buscar('[data-skus]');s.replaceChildren();opcion(s,'Selecciona una presentación','');skus.filter(x=>String(x.variante_id || '')===buscar('[data-variantes]').value).forEach(x=>{const o=opcion(s,`${x.presentacion} · ${x.sku} · ${Number(x.precio_unitario)>0?'S/ '+Number(x.precio_unitario).toFixed(2):'Falta precio unitario'}`,x.id);o.disabled=!(Number(x.precio_unitario)>0);});}
    for(const tipo of ['clientes','productos']) {let timer;buscar(tipo==='clientes'?'[data-buscar-cliente]':'[data-buscar-producto]').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>consultar(tipo),250);});buscar(`[data-mas-${tipo}]`).addEventListener('click',()=>consultar(tipo,true));}
    buscar('[data-productos]').addEventListener('change',()=>{skus=[];renderVariantes();if(buscar('[data-productos]').value)consultar('skus');});
    buscar('[data-variantes]').addEventListener('change',renderSkus);buscar('[data-mas-skus]').addEventListener('click',()=>consultar('skus',true));
    buscar('[data-agregar-linea]').addEventListener('click',()=>{
        const sku=skus.find(s=>Number(s.id)===Number(buscar('[data-skus]').value)), n=Number(buscar('[data-cantidad-nueva]').value);
        if(!sku || !Number.isSafeInteger(n) || n<1){mensaje.textContent='Selecciona presentación y cantidad entera positiva.';return;}
        const existente=lineas.find(l=>Number(l.presentacion_producto_id)===Number(sku.id));
        if(existente)existente.cantidad+=n;else {if(lineas.length>=18){mensaje.textContent='La nota admite hasta 18 líneas comerciales.';return;}lineas.push({presentacion_producto_id:sku.id,nombre:`${sku.producto}${sku.variante?' · '+sku.variante:''} · ${sku.presentacion}`,sku:sku.sku,cantidad:n,cantidad_espera:0,precio_unitario:sku.precio_unitario});}
        renderLineas();renderGrupos();
    });
    buscar('[data-empaquetar]').checked=grupos.length>0;buscar('[data-grupos-panel]').hidden=!grupos.length;
    buscar('[data-empaquetar]').addEventListener('change',e=>{if(!e.target.checked && grupos.length && !confirm('¿Entregar todas las unidades sueltas y retirar las agrupaciones vigentes?')){e.target.checked=true;return;}if(!e.target.checked)grupos=[];buscar('[data-grupos-panel]').hidden=!e.target.checked;renderGrupos();resumen();});
    buscar('[data-agregar-grupo]').addEventListener('click',()=>{grupos.push({capacidad:1,componentes:[]});renderGrupos();resumen();});
    form.addEventListener('submit',e=>{if(!lineas.length || !resumen()){e.preventDefault();mensaje.textContent ||= 'Añade al menos una línea.';mensaje.focus();}});
    renderLineas();renderGrupos();consultar('clientes');consultar('productos');
})(typeof globalThis!=='undefined'?globalThis:this);
