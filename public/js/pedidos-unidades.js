(function (global) {
    'use strict';
    function resumir(lineas, grupos) {
        let unidades = 0, centimos = 0, espera = 0, agrupadas = 0;
        const disponibles = new Map(), asignadas = new Map();
        for (const l of lineas) {
            const n = Number(l.cantidad), e = Number(l.cantidad_espera || 0), precio = Number(l.precio_unitario);
            if (!Number.isSafeInteger(n) || n < 0 || !Number.isSafeInteger(e) || e < 0 || e > n) throw new Error('Indica unidades enteras y una espera que no supere lo solicitado.');
            if (!Number.isFinite(precio) || precio <= 0) throw new Error('Falta un precio unitario válido.');
            if (n * Math.round(precio * 100) > 99999999) throw new Error('El importe de una línea supera S/ 999 999,99.');
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
    function copiar(grupo) { const copia = JSON.parse(JSON.stringify(grupo)); delete copia.identidad; return copia; }
    global.PedidosUnidadesUtilidades = {resumir, copiar};
    if (typeof module !== 'undefined' && module.exports) module.exports = {resumir, copiar};
    if (typeof document === 'undefined') return;
    const form = document.querySelector('[data-pedido-unidades]'); if (!form) return;
    const inicial = JSON.parse(document.getElementById('pedido-unidades-inicial').textContent);
    const lineas = (inicial.lineas || []).map(l => ({...l, cantidad:Number(l.cantidad), cantidad_espera:Number(l.cantidad_espera || 0)}));
    let grupos = Array.isArray(inicial.agrupaciones) ? inicial.agrupaciones.map(g => ({identidad:g.identidad, capacidad:g.capacidad, componentes:g.componentes.map(c => ({sku_id:Number(c.sku_id),cantidad:Number(c.cantidad)}))})) : [];
    let grupoActual = 0;
    let skus = [], paginas = {};
    const buscar = s => form.querySelector(s), mensaje = buscar('[data-mensaje]');
    function opcion(select, texto, valor) { const o=document.createElement('option'); o.textContent=texto; o.value=valor; select.append(o); return o; }
    function resumen() {
        buscar('[data-agrupaciones-json]').value=JSON.stringify(grupos);
        form.querySelectorAll('[data-total-grupo]').forEach(salida => {
            const grupo = grupos[Number(salida.dataset.totalGrupo)];
            salida.textContent = `Contenido declarado: ${grupo.componentes.reduce((s,c)=>s+Number(c.cantidad),0)} de ${grupo.capacidad} unidades. Si no llena la capacidad, queda expresamente incompleto.`;
        });
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
            const precio=document.createElement('p');precio.textContent=`Precio unitario ${l.id?'pactado':'del catálogo'} S/ ${Number(l.precio_unitario).toFixed(2)}`;campos.append(precio);
            const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-secondary';quitar.textContent='Retirar línea';
            quitar.addEventListener('click',()=>{if(l.id){l.cantidad=0;l.cantidad_espera=0;}else lineas.splice(i,1);renderLineas();renderGrupos();resumen();});campos.append(quitar);
            buscar('[data-lineas]').append(card);
        });
        resumen();
    }
    function renderGrupos() {
        buscar('[data-grupos]').replaceChildren();
        grupoActual = Math.max(0, Math.min(grupoActual, grupos.length - 1));
        if (grupos.length) {
            const controles = document.createElement('div'); controles.className = 'unidades-campos';
            for (const [texto, desplazamiento] of [['Paquete anterior', -1], ['Paquete siguiente', 1]]) {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-secondary'; b.textContent = texto;
                b.disabled = grupoActual + desplazamiento < 0 || grupoActual + desplazamiento >= grupos.length;
                b.addEventListener('click', () => { grupoActual += desplazamiento; renderGrupos(); }); controles.append(b);
            }
            const label = document.createElement('label'); label.textContent = `Paquete a editar (de ${grupos.length})`;
            const numero = document.createElement('input'); numero.type = 'number'; numero.min = '1'; numero.max = String(grupos.length); numero.step = '1'; numero.value = String(grupoActual + 1); numero.className = 'form-control';
            numero.addEventListener('change', () => { if (Number.isInteger(Number(numero.value)) && Number(numero.value) >= 1 && Number(numero.value) <= grupos.length) { grupoActual = Number(numero.value) - 1; renderGrupos(); } else { numero.value = String(grupoActual + 1); } });
            label.append(numero); controles.append(label); buscar('[data-grupos]').append(controles);
        }
        grupos.slice(grupoActual, grupoActual + 1).forEach((g)=>{
            const i = grupoActual;
            const despachado = (inicial.bloqueadas || []).includes(g.identidad);
            const card=document.createElement('div');card.className='unidades-grupo';const titulo=document.createElement('h3');titulo.textContent=`Paquete ${i+1}`;card.append(titulo);
            const capacidad=document.createElement('label');capacidad.textContent='Capacidad máxima del paquete';const inp=document.createElement('input');inp.className='form-control';inp.type='number';inp.min='1';inp.step='1';inp.value=g.capacidad;inp.addEventListener('input',()=>{g.capacidad=Number(inp.value);resumen();});capacidad.append(inp);card.append(capacidad);
            g.componentes.forEach((c,j)=>{
                const fila=document.createElement('div');fila.className='unidades-campos';const select=document.createElement('select');select.className='form-control';select.setAttribute('aria-label','SKU del paquete');opcion(select,'Selecciona un SKU','');
                const referencia = lineas.find(l=>Number(l.presentacion_producto_id)===Number(g.componentes.find(x=>Number(x.sku_id)>0)?.sku_id));
                lineas.filter(l=>l.cantidad>0 && (!referencia?.compatibilidad || JSON.stringify(l.compatibilidad)===JSON.stringify(referencia.compatibilidad))).forEach(l=>opcion(select,[l.nombre,l.sku].filter(Boolean).join(' · ') || `SKU ${l.presentacion_producto_id}`,l.presentacion_producto_id));select.value=c.sku_id || '';select.addEventListener('change',()=>{c.sku_id=Number(select.value);renderGrupos();resumen();});fila.append(select);
                const n=document.createElement('input');n.type='number';n.min='1';n.step='1';n.className='form-control';n.value=c.cantidad;n.setAttribute('aria-label','Unidades dentro del paquete');n.addEventListener('input',()=>{c.cantidad=Number(n.value);resumen();});fila.append(n);
                const quitar=document.createElement('button');quitar.type='button';quitar.className='btn btn-secondary';quitar.textContent='Quitar componente';quitar.addEventListener('click',()=>{g.componentes.splice(j,1);renderGrupos();resumen();});fila.append(quitar);card.append(fila);
            });
            const total=document.createElement('p');total.dataset.totalGrupo=String(i);total.textContent=`Contenido declarado: ${g.componentes.reduce((s,c)=>s+Number(c.cantidad),0)} unidades. Si no llena la capacidad, el paquete queda expresamente incompleto.`;card.append(total);
            for(const [texto,accion] of [['Añadir variante al paquete',()=>g.componentes.push({sku_id:0,cantidad:1})],['Copiar este paquete',()=>{if(grupos.length<1000){grupos.push(copiar(g));grupoActual=grupos.length-1;}}],['Eliminar paquete',()=>grupos.splice(i,1)]]) {
                const b=document.createElement('button');b.type='button';b.className='btn btn-secondary';b.textContent=texto;b.addEventListener('click',()=>{accion();renderGrupos();resumen();});card.append(b);
            }
            if (despachado) {
                const aviso = document.createElement('p'); aviso.textContent = 'Paquete ya despachado: su composición física se conserva. Puedes copiarla para definir otro paquete.'; card.prepend(aviso);
                card.querySelectorAll('input,select,button').forEach(control => { if (control.textContent !== 'Copiar este paquete') control.disabled = true; });
            }
            buscar('[data-grupos]').append(card);
        });
    }
    let consultaSku, generacionSku = 0, cargandoSku = false;
    async function consultarSkus(mas = false) {
        if (mas && cargandoSku) return;
        consultaSku?.abort(); const generacion = ++generacionSku; consultaSku = new AbortController(); cargandoSku = true;
        const estado = buscar('[data-estado-skus]'), boton = buscar('[data-mas-skus]'); boton.disabled = true; estado.textContent = 'Cargando presentaciones…';
        const producto = buscar('[data-productos]').value;
        const url = new URL(form.dataset.catalogo, location.origin); url.searchParams.set('tipo', 'skus'); url.searchParams.set('producto_id', producto); url.searchParams.set('page', mas ? (paginas.skus || 2) : 1);
        try {
            const response = await fetch(url, { signal: consultaSku.signal, headers: { Accept: 'application/json' } });
            if (!response.ok || response.redirected) throw new Error('No se pudieron consultar las presentaciones. Revisa tu sesión.');
            const data = await response.json(); if (generacion !== generacionSku || producto !== buscar('[data-productos]').value) return;
            skus = mas ? skus.concat(data.data.filter(s => !skus.some(p => p.id === s.id))) : data.data; renderVariantes();
            paginas.skus = data.current_page + 1; boton.hidden = !data.next_page_url;
            estado.textContent = skus.length ? `${skus.length} presentaciones disponibles cargadas.${data.next_page_url ? ' Puedes consultar más.' : ''}` : 'Este producto no tiene presentaciones activas con precio válido.';
        } catch (e) { if (generacion === generacionSku && e.name !== 'AbortError') estado.textContent = e.message; }
        finally { if (generacion === generacionSku) { cargandoSku = false; boton.disabled = false; } }
    }
    function renderVariantes() {
        const vs=buscar('[data-variantes]'), previo=vs.value;vs.replaceChildren();const variantes=new Map(skus.map(s=>[String(s.variante_id || ''),s.variante || 'Sin variante']));variantes.forEach((v,k)=>opcion(vs,v,k));if(variantes.has(previo))vs.value=previo;
        buscar('[data-campo-variante]').hidden=!skus.some(s=>s.variante_id);renderSkus();
    }
    function renderSkus(){const s=buscar('[data-skus]');s.replaceChildren();opcion(s,'Selecciona una presentación','');skus.filter(x=>String(x.variante_id || '')===buscar('[data-variantes]').value).forEach(x=>{const o=opcion(s,`${x.presentacion} · ${x.sku} · ${Number(x.precio_unitario)>0?'S/ '+Number(x.precio_unitario).toFixed(2):'Falta precio unitario'}`,x.id);o.disabled=!(Number(x.precio_unitario)>0);});}
    for (const tipo of ['clientes', 'productos']) {
        global.PedidosAutocompletar.crear({ input: buscar(tipo === 'clientes' ? '[data-buscar-cliente]' : '[data-buscar-producto]'), select: buscar(tipo === 'clientes' ? '[data-clientes]' : '[data-productos]'), lista: buscar(`[data-coincidencias-${tipo}]`), estado: buscar(`[data-estado-${tipo}]`), mas: buscar(`[data-mas-${tipo}]`), url: form.dataset.catalogo, tipo });
    }
    buscar('[data-productos]').addEventListener('change', () => {
        consultaSku?.abort(); generacionSku++; cargandoSku = false; skus = []; buscar('[data-mas-skus]').hidden = true; buscar('[data-estado-skus]').textContent = ''; renderVariantes();
        if (buscar('[data-productos]').value) consultarSkus();
    });
    buscar('[data-variantes]').addEventListener('change', renderSkus); buscar('[data-mas-skus]').addEventListener('click', () => consultarSkus(true));
    buscar('[data-agregar-linea]').addEventListener('click',()=>{
        const sku=skus.find(s=>Number(s.id)===Number(buscar('[data-skus]').value)), n=Number(buscar('[data-cantidad-nueva]').value);
        if(!sku || !Number.isSafeInteger(n) || n<1){mensaje.textContent='Selecciona presentación y cantidad entera positiva.';return;}
        const existente=lineas.find(l=>Number(l.presentacion_producto_id)===Number(sku.id));
        if(existente)existente.cantidad+=n;else {if(lineas.length>=18){mensaje.textContent='La nota admite hasta 18 líneas comerciales.';return;}lineas.push({presentacion_producto_id:sku.id,nombre:`${sku.producto}${sku.variante?' · '+sku.variante:''} · ${sku.presentacion}`,sku:sku.sku,cantidad:n,cantidad_espera:0,precio_unitario:sku.precio_unitario,compatibilidad:sku.compatibilidad});}
        renderLineas();renderGrupos();
    });
    buscar('[data-empaquetar]').checked=grupos.length>0;buscar('[data-grupos-panel]').hidden=!grupos.length;
    buscar('[data-empaquetar]').addEventListener('change',e=>{if(!e.target.checked && (inicial.bloqueadas || []).length){e.target.checked=true;mensaje.textContent='Se conservan las agrupaciones que ya fueron despachadas.';return;}if(!e.target.checked && grupos.length && !confirm('¿Entregar todas las unidades sueltas y retirar las agrupaciones vigentes?')){e.target.checked=true;return;}if(!e.target.checked)grupos=[];buscar('[data-grupos-panel]').hidden=!e.target.checked;renderGrupos();resumen();});
    buscar('[data-agregar-grupo]').addEventListener('click',()=>{if(grupos.length>=1000){mensaje.textContent='El formulario admite hasta 1000 agrupaciones por versión.';return;}grupos.push({capacidad:1,componentes:[]});grupoActual=grupos.length-1;renderGrupos();resumen();});
    form.addEventListener('submit',e=>{if(!lineas.length || !resumen()){e.preventDefault();mensaje.textContent ||= 'Añade al menos una línea.';mensaje.focus();}});
    renderLineas();renderGrupos();
    if (inicial.seleccionSku && buscar('[data-productos]').value) {
        skus = [inicial.seleccionSku]; renderVariantes(); buscar('[data-variantes]').value = String(inicial.seleccionSku.variante_id || ''); renderSkus(); buscar('[data-skus]').value = String(inicial.seleccionSku.id);
        buscar('[data-mas-skus]').hidden = false; paginas.skus = 1;
    } else if (buscar('[data-productos]').value) consultarSkus();
})(typeof globalThis!=='undefined'?globalThis:this);
