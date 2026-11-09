@if(request()->attributes->get('demostracion_verificada') === true)
<div role="status" style="padding:14px 20px;margin-bottom:20px;border:2px solid #8a5200;border-radius:10px;background:#fff3cf;color:#563500;font-size:14px;font-weight:700;line-height:1.5;overflow-wrap:anywhere;">DEMOSTRACIÓN · Datos ficticios · Sin información real de clientes</div>
@endif
