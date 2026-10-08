<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use App\Models\VarianteProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $productos = Producto::query()
            ->with('categoria')
            ->withCount(['variantes', 'presentaciones'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($subquery) use ($search) {
                    $subquery
                        ->where('codigo', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%")
                        ->orWhere('marca', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $query->where('categoria_id', $request->integer('categoria'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('estado', $request->input('status') === '1');
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $categorias = Categoria::orderBy('nombre')->get();

        return view('products.index', compact('productos', 'categorias'));
    }

    public function create(): View
    {
        $categorias = Categoria::where('estado', true)
            ->orderBy('nombre')
            ->get();

        $codigoSugerido = $this->generarCodigoSugerido();

        return view('products.create', compact('categorias', 'codigoSugerido'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validarFormulario($request);

        $producto = DB::transaction(function () use ($request, $validated) {
            $dataProducto = [
                'codigo' => $validated['codigo'],
                'categoria_id' => $validated['categoria_id'],
                'nombre' => $validated['nombre'],
                'marca' => $validated['marca'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'estado' => (bool) $validated['estado'],
            ];

            if ($request->hasFile('imagen_referencia')) {
                $dataProducto['imagen_referencia'] = $request
                    ->file('imagen_referencia')
                    ->store('productos', 'public');
            }

            $producto = Producto::create($dataProducto);

            $mapaVariantes = [];

            foreach ($validated['variantes'] ?? [] as $variante) {
                $creada = $producto->variantes()->create([
                    'tipo' => $variante['tipo'],
                    'valor' => $variante['valor'],
                    'estado' => (bool) ($variante['estado'] ?? true),
                ]);

                $mapaVariantes[$variante['clave']] = $creada->id;
            }

            foreach ($validated['presentaciones'] as $presentacion) {
                $producto->presentaciones()->create(
                    $this->datosPresentacion($presentacion, $mapaVariantes)
                );
            }

            return $producto;
        });

        return redirect()
            ->route('productos.show', $producto)
            ->with('success', 'El producto fue registrado correctamente.');
    }

    public function show(Producto $producto): View
    {
        $producto->load([
            'categoria',
            'variantes' => fn ($query) => $query->orderBy('tipo')->orderBy('valor'),
            'presentaciones' => fn ($query) => $query->with('variante')->orderBy('id'),
        ]);

        return view('products.show', compact('producto'));
    }

    public function edit(Producto $producto): View
    {
        $producto->load(['variantes', 'presentaciones']);

        $categorias = Categoria::query()
            ->where('estado', true)
            ->orWhere('id', $producto->categoria_id)
            ->orderBy('nombre')
            ->get();

        return view('products.edit', compact('producto', 'categorias'));
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $validated = $this->validarFormulario($request, $producto);

        DB::transaction(function () use ($request, $validated, $producto) {
            $dataProducto = [
                'codigo' => $validated['codigo'],
                'categoria_id' => $validated['categoria_id'],
                'nombre' => $validated['nombre'],
                'marca' => $validated['marca'] ?? null,
                'descripcion' => $validated['descripcion'] ?? null,
                'estado' => (bool) $validated['estado'],
            ];

            if ($request->boolean('eliminar_imagen') && $producto->imagen_referencia) {
                Storage::disk('public')->delete($producto->imagen_referencia);
                $dataProducto['imagen_referencia'] = null;
            }

            if ($request->hasFile('imagen_referencia')) {
                if ($producto->imagen_referencia) {
                    Storage::disk('public')->delete($producto->imagen_referencia);
                }

                $dataProducto['imagen_referencia'] = $request
                    ->file('imagen_referencia')
                    ->store('productos', 'public');
            }

            $producto->update($dataProducto);

            $mapaVariantes = [];
            $idsVariantesConservadas = collect($validated['variantes'] ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            // Eliminamos primero las variantes retiradas del formulario.
            // La FK de presentaciones usa nullOnDelete, por lo que las presentaciones
            // existentes se conservan y luego se actualizan con la nueva selección.
            if (empty($idsVariantesConservadas)) {
                $producto->variantes()->delete();
            } else {
                $producto->variantes()
                    ->whereNotIn('id', $idsVariantesConservadas)
                    ->delete();
            }

            foreach ($validated['variantes'] ?? [] as $variante) {
                if (!empty($variante['id'])) {
                    $modeloVariante = $producto->variantes()
                        ->whereKey($variante['id'])
                        ->first();

                    if (!$modeloVariante) {
                        throw ValidationException::withMessages([
                            'variantes' => 'Una de las variantes no pertenece al producto.',
                        ]);
                    }

                    $modeloVariante->update([
                        'tipo' => $variante['tipo'],
                        'valor' => $variante['valor'],
                        'estado' => (bool) ($variante['estado'] ?? true),
                    ]);
                } else {
                    $modeloVariante = $producto->variantes()->create([
                        'tipo' => $variante['tipo'],
                        'valor' => $variante['valor'],
                        'estado' => (bool) ($variante['estado'] ?? true),
                    ]);
                }

                $mapaVariantes[$variante['clave']] = $modeloVariante->id;
            }

            $idsPresentacionesConservadas = [];

            foreach ($validated['presentaciones'] as $presentacion) {
                $datos = $this->datosPresentacion($presentacion, $mapaVariantes);

                if (!empty($presentacion['id'])) {
                    $modeloPresentacion = $producto->presentaciones()
                        ->whereKey($presentacion['id'])
                        ->first();

                    if (!$modeloPresentacion) {
                        throw ValidationException::withMessages([
                            'presentaciones' => 'Una de las presentaciones no pertenece al producto.',
                        ]);
                    }

                    $modeloPresentacion->update($datos);
                } else {
                    $modeloPresentacion = $producto->presentaciones()->create($datos);
                }

                $idsPresentacionesConservadas[] = $modeloPresentacion->id;
            }

            $producto->presentaciones()
                ->whereNotIn('id', $idsPresentacionesConservadas)
                ->delete();

        });

        return redirect()
            ->route('productos.show', $producto)
            ->with('success', 'El producto fue actualizado correctamente.');
    }

    public function toggleStatus(Producto $producto): RedirectResponse
    {
        $producto->update([
            'estado' => !$producto->estado,
        ]);

        return back()->with(
            'success',
            $producto->estado
                ? 'El producto fue activado correctamente.'
                : 'El producto fue desactivado correctamente.'
        );
    }

    private function validarFormulario(Request $request, ?Producto $producto = null): array
    {
        $validator = validator($request->all(), [
            'codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('productos', 'codigo')->ignore($producto?->id),
            ],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'marca' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'imagen_referencia' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'eliminar_imagen' => ['nullable', 'boolean'],
            'estado' => ['required', 'boolean'],

            'variantes' => ['nullable', 'array'],
            'variantes.*.id' => ['nullable', 'integer', 'exists:variantes_producto,id'],
            'variantes.*.clave' => ['required', 'string', 'max:80'],
            'variantes.*.tipo' => ['required', 'string', 'max:50'],
            'variantes.*.valor' => ['required', 'string', 'max:100'],
            'variantes.*.estado' => ['required', 'boolean'],

            'presentaciones' => ['required', 'array', 'min:1'],
            'presentaciones.*.id' => ['nullable', 'integer', 'exists:presentaciones_producto,id'],
            'presentaciones.*.codigo_sku' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request, $producto) {
                    $partes = explode('.', $attribute);
                    $indice = $partes[1] ?? null;
                    $idPresentacion = $indice !== null
                        ? $request->input("presentaciones.{$indice}.id")
                        : null;

                    $query = PresentacionProducto::where('codigo_sku', $value);

                    if ($idPresentacion) {
                        $query->where('id', '!=', $idPresentacion);
                    }

                    if ($query->exists()) {
                        $fail('El código SKU ya está registrado.');
                    }
                },
            ],
            'presentaciones.*.variante_clave' => ['nullable', 'string', 'max:80'],
            'presentaciones.*.tipo_envase' => ['nullable', 'string', 'max:60'],
            'presentaciones.*.contenido' => ['required', 'numeric', 'gt:0'],
            'presentaciones.*.unidad_medida' => ['required', Rule::in(['L', 'ml', 'kg', 'g'])],
            'presentaciones.*.tipo_empaque' => ['nullable', Rule::in(['Paquete', 'Caja'])],
            'presentaciones.*.unidades_por_empaque' => ['nullable', 'integer', 'min:1'],
            'presentaciones.*.venta_por_unidad' => ['required', 'boolean'],
            'presentaciones.*.venta_por_empaque' => ['required', 'boolean'],
            'presentaciones.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'presentaciones.*.precio_empaque' => ['nullable', 'numeric', 'min:0'],
            'presentaciones.*.estado' => ['required', 'boolean'],
        ], [
            'presentaciones.required' => 'Debes registrar al menos una presentación.',
            'presentaciones.min' => 'Debes registrar al menos una presentación.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $variantes = $request->input('variantes', []);
            $clavesVariantes = collect($variantes)->pluck('clave')->filter()->all();
            $combinaciones = [];

            foreach ($variantes as $indice => $variante) {
                $tipo = mb_strtolower(trim((string) ($variante['tipo'] ?? '')));
                $valor = mb_strtolower(trim((string) ($variante['valor'] ?? '')));
                $combinacion = "{$tipo}|{$valor}";

                if ($tipo !== '' && $valor !== '') {
                    if (isset($combinaciones[$combinacion])) {
                        $validator->errors()->add(
                            "variantes.{$indice}.valor",
                            'No puedes repetir la misma variante.'
                        );
                    }

                    $combinaciones[$combinacion] = true;
                }
            }

            $skus = [];

            foreach ($request->input('presentaciones', []) as $indice => $presentacion) {
                $sku = mb_strtolower(trim((string) ($presentacion['codigo_sku'] ?? '')));

                if ($sku !== '') {
                    if (isset($skus[$sku])) {
                        $validator->errors()->add(
                            "presentaciones.{$indice}.codigo_sku",
                            'No puedes repetir el mismo código SKU dentro del producto.'
                        );
                    }

                    $skus[$sku] = true;
                }

                $vendeUnidad = (int) ($presentacion['venta_por_unidad'] ?? 0) === 1;
                $vendeEmpaque = (int) ($presentacion['venta_por_empaque'] ?? 0) === 1;
                $varianteClave = $presentacion['variante_clave'] ?? null;

                if (!$vendeUnidad && !$vendeEmpaque) {
                    $validator->errors()->add(
                        "presentaciones.{$indice}.venta_por_unidad",
                        'Cada presentación debe venderse por unidad, por empaque o por ambas formas.'
                    );
                }

                if ($vendeEmpaque) {
                    if (empty($presentacion['tipo_empaque'])) {
                        $validator->errors()->add(
                            "presentaciones.{$indice}.tipo_empaque",
                            'Selecciona si el empaque es Paquete o Caja.'
                        );
                    }

                    if (empty($presentacion['unidades_por_empaque']) || (int) $presentacion['unidades_por_empaque'] < 1) {
                        $validator->errors()->add(
                            "presentaciones.{$indice}.unidades_por_empaque",
                            'Indica cuántas unidades contiene el empaque.'
                        );
                    }
                }

                if ($varianteClave && !in_array($varianteClave, $clavesVariantes, true)) {
                    $validator->errors()->add(
                        "presentaciones.{$indice}.variante_clave",
                        'La variante seleccionada ya no existe.'
                    );
                }
            }
        });

        return $validator->validate();
    }

    private function datosPresentacion(array $presentacion, array $mapaVariantes): array
    {
        $vendeUnidad = (bool) ($presentacion['venta_por_unidad'] ?? false);
        $vendeEmpaque = (bool) ($presentacion['venta_por_empaque'] ?? false);
        $varianteClave = $presentacion['variante_clave'] ?? null;

        return [
            'variante_producto_id' => $varianteClave
                ? ($mapaVariantes[$varianteClave] ?? null)
                : null,
            'codigo_sku' => $presentacion['codigo_sku'],
            'tipo_envase' => $presentacion['tipo_envase'] ?? null,
            'contenido' => $presentacion['contenido'],
            'unidad_medida' => $presentacion['unidad_medida'],
            'tipo_empaque' => $vendeEmpaque ? ($presentacion['tipo_empaque'] ?? null) : null,
            'unidades_por_empaque' => $vendeEmpaque ? ($presentacion['unidades_por_empaque'] ?? null) : null,
            'venta_por_unidad' => $vendeUnidad,
            'venta_por_empaque' => $vendeEmpaque,
            'precio_unitario' => $vendeUnidad ? ($presentacion['precio_unitario'] ?? null) : null,
            'precio_empaque' => $vendeEmpaque ? ($presentacion['precio_empaque'] ?? null) : null,
            'estado' => (bool) ($presentacion['estado'] ?? true),
        ];
    }

    private function generarCodigoSugerido(): string
    {
        $siguiente = (Producto::max('id') ?? 0) + 1;

        return 'PRD'.str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }
}
