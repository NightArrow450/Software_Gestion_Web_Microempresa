<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\PresentacionProducto;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

            $productoActivo = (bool) $validated['estado'];

            $mapaVariantes = $this->guardarVariantes(
                $producto,
                $validated['variantes'] ?? [],
                false,
                $productoActivo
            );

            foreach ($validated['presentaciones'] as $presentacion) {
                foreach ($this->expandirPresentacion(
                    $presentacion,
                    $mapaVariantes,
                    $validated['prefijo_sku'],
                    $productoActivo
                ) as $datos) {
                    unset($datos['_clave_relacion']);
                    $producto->presentaciones()->create($datos);
                }
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

            $idsVariantesConservadas = collect($validated['variantes'] ?? [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($idsVariantesConservadas)) {
                $producto->variantes()->delete();
            } else {
                $producto->variantes()
                    ->whereNotIn('id', $idsVariantesConservadas)
                    ->delete();
            }

            $productoActivo = (bool) $validated['estado'];

            $mapaVariantes = $this->guardarVariantes(
                $producto,
                $validated['variantes'] ?? [],
                true,
                $productoActivo
            );

            $idsPresentacionesConservadas = [];

            foreach ($validated['presentaciones'] as $presentacion) {
                $idsExistentes = $presentacion['ids'] ?? [];

                foreach ($this->expandirPresentacion(
                    $presentacion,
                    $mapaVariantes,
                    $validated['prefijo_sku'],
                    $productoActivo
                ) as $datosExpandidos) {
                    $claveRelacion = $datosExpandidos['_clave_relacion'];
                    unset($datosExpandidos['_clave_relacion']);

                    $idExistente = $idsExistentes[$claveRelacion] ?? null;

                    if ($idExistente) {
                        $modelo = $producto->presentaciones()
                            ->whereKey($idExistente)
                            ->first();

                        if (!$modelo) {
                            throw ValidationException::withMessages([
                                'presentaciones' => 'Una de las presentaciones no pertenece al producto.',
                            ]);
                        }

                        $modelo->update($datosExpandidos);
                    } else {
                        $modelo = $producto->presentaciones()->create($datosExpandidos);
                    }

                    $idsPresentacionesConservadas[] = $modelo->id;
                }
            }

            if (empty($idsPresentacionesConservadas)) {
                $producto->presentaciones()->delete();
            } else {
                $producto->presentaciones()
                    ->whereNotIn('id', $idsPresentacionesConservadas)
                    ->delete();
            }

            if (!$productoActivo) {
                $producto->variantes()->update(['estado' => false]);
                $producto->presentaciones()->update(['estado' => false]);
            }
        });

        return redirect()
            ->route('productos.show', $producto)
            ->with('success', 'El producto fue actualizado correctamente.');
    }

    public function toggleStatus(Producto $producto): RedirectResponse
    {
        DB::transaction(function () use ($producto) {
            $nuevoEstado = !$producto->estado;

            $producto->update([
                'estado' => $nuevoEstado,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Cascada de estado
            |--------------------------------------------------------------------------
            |
            | Si el producto queda inactivo, ninguna variante ni presentación
            | puede permanecer activa. Al reactivar el producto, los hijos se
            | mantienen inactivos para que el usuario decida cuáles volver a activar.
            |
            */
            if (!$nuevoEstado) {
                $producto->variantes()->update(['estado' => false]);
                $producto->presentaciones()->update(['estado' => false]);
            }
        });

        return back()->with(
            'success',
            $producto->fresh()->estado
                ? 'El producto fue activado correctamente. Sus variantes y presentaciones permanecen con su estado actual.'
                : 'El producto fue desactivado junto con todas sus variantes y presentaciones.'
        );
    }

    private function validarFormulario(Request $request, ?Producto $producto = null): array
    {
        $request->merge([
            'prefijo_sku' => $this->normalizarPrefijo((string) $request->input('prefijo_sku')),
        ]);

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
            'prefijo_sku' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],

            'variantes' => ['nullable', 'array'],
            'variantes.*.id' => ['nullable', 'integer', 'exists:variantes_producto,id'],
            'variantes.*.clave' => ['required', 'string', 'max:80'],
            'variantes.*.tipo' => ['required', 'string', 'max:50'],
            'variantes.*.valor' => ['required', 'string', 'max:100'],
            'variantes.*.estado' => ['required', 'boolean'],

            'presentaciones' => ['required', 'array', 'min:1'],
            'presentaciones.*.ids' => ['nullable', 'array'],
            'presentaciones.*.ids.*' => ['nullable', 'integer', 'exists:presentaciones_producto,id'],
            'presentaciones.*.variantes_clave' => ['nullable', 'array'],
            'presentaciones.*.variantes_clave.*' => ['string', 'max:80'],
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
            'prefijo_sku.required' => 'No se pudo generar el prefijo SKU. Ingresa el nombre y la marca del producto o escribe el prefijo manualmente.',
            'prefijo_sku.regex' => 'El prefijo SKU solo puede contener letras, números y guiones.',
            'presentaciones.required' => 'Debes registrar al menos una presentación.',
            'presentaciones.min' => 'Debes registrar al menos una presentación.',
        ]);

        $validator->after(function ($validator) use ($request, $producto) {
            $variantes = $request->input('variantes', []);
            $clavesVariantes = collect($variantes)->pluck('clave')->filter()->all();
            $datosVariantes = collect($variantes)->keyBy('clave');
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

            $skusGenerados = [];
            $idsConservados = collect($request->input('presentaciones', []))
                ->flatMap(fn ($presentacion) => array_values($presentacion['ids'] ?? []))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            foreach ($request->input('presentaciones', []) as $indice => $presentacion) {
                $vendeUnidad = (int) ($presentacion['venta_por_unidad'] ?? 0) === 1;
                $vendeEmpaque = (int) ($presentacion['venta_por_empaque'] ?? 0) === 1;
                $seleccionadas = array_values(array_unique(array_filter($presentacion['variantes_clave'] ?? [])));

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

                foreach ($seleccionadas as $clave) {
                    if (!in_array($clave, $clavesVariantes, true)) {
                        $validator->errors()->add(
                            "presentaciones.{$indice}.variantes_clave",
                            'Una de las variantes seleccionadas ya no existe.'
                        );
                    }
                }

                $clavesObjetivo = empty($seleccionadas) ? [null] : $seleccionadas;

                foreach ($clavesObjetivo as $clave) {
                    $valorVariante = $clave ? (string) ($datosVariantes[$clave]['valor'] ?? '') : null;
                    $sku = $this->generarSku(
                        (string) $request->input('prefijo_sku'),
                        $valorVariante,
                        $presentacion['contenido'] ?? null,
                        $presentacion['unidad_medida'] ?? null
                    );

                    if (isset($skusGenerados[$sku])) {
                        $validator->errors()->add(
                            "presentaciones.{$indice}.contenido",
                            "La combinación genera un SKU duplicado: {$sku}."
                        );
                    }

                    $skusGenerados[$sku] = true;
                }
            }

            if (!empty($skusGenerados)) {
                $query = PresentacionProducto::whereIn('codigo_sku', array_keys($skusGenerados));

                if (!empty($idsConservados)) {
                    $query->whereNotIn('id', $idsConservados);
                }

                if ($producto) {
                    $query->where('producto_id', '!=', $producto->id);
                }

                $existentes = $query->pluck('codigo_sku')->all();

                foreach ($existentes as $sku) {
                    $validator->errors()->add(
                        'presentaciones',
                        "El código SKU {$sku} ya está registrado en otro producto."
                    );
                }
            }
        });

        return $validator->validate();
    }

    private function guardarVariantes(
        Producto $producto,
        array $variantes,
        bool $actualizando = false,
        bool $productoActivo = true
    ): array
    {
        $mapa = [];

        foreach ($variantes as $variante) {
            if ($actualizando && !empty($variante['id'])) {
                $modelo = $producto->variantes()
                    ->whereKey($variante['id'])
                    ->first();

                if (!$modelo) {
                    throw ValidationException::withMessages([
                        'variantes' => 'Una de las variantes no pertenece al producto.',
                    ]);
                }

                $modelo->update([
                    'tipo' => $variante['tipo'],
                    'valor' => $variante['valor'],
                    'estado' => $productoActivo && (bool) ($variante['estado'] ?? true),
                ]);
            } else {
                $modelo = $producto->variantes()->create([
                    'tipo' => $variante['tipo'],
                    'valor' => $variante['valor'],
                    'estado' => $productoActivo && (bool) ($variante['estado'] ?? true),
                ]);
            }

            $mapa[$variante['clave']] = [
                'id' => $modelo->id,
                'valor' => $modelo->valor,
            ];
        }

        return $mapa;
    }

    private function expandirPresentacion(
        array $presentacion,
        array $mapaVariantes,
        string $prefijoSku,
        bool $productoActivo = true
    ): array
    {
        $seleccionadas = array_values(array_unique(array_filter($presentacion['variantes_clave'] ?? [])));
        $clavesObjetivo = empty($seleccionadas) ? [null] : $seleccionadas;
        $resultado = [];

        foreach ($clavesObjetivo as $clave) {
            $variante = $clave ? ($mapaVariantes[$clave] ?? null) : null;

            $datos = $this->datosPresentacion(
                $presentacion,
                $variante['id'] ?? null,
                $this->generarSku(
                    $prefijoSku,
                    $variante['valor'] ?? null,
                    $presentacion['contenido'],
                    $presentacion['unidad_medida']
                ),
                $productoActivo
            );

            $datos['_clave_relacion'] = $clave ?: '__none__';
            $resultado[] = $datos;
        }

        return $resultado;
    }

    private function datosPresentacion(
        array $presentacion,
        ?int $varianteId,
        string $sku,
        bool $productoActivo = true
    ): array
    {
        $vendeUnidad = (bool) ($presentacion['venta_por_unidad'] ?? false);
        $vendeEmpaque = (bool) ($presentacion['venta_por_empaque'] ?? false);

        return [
            'variante_producto_id' => $varianteId,
            'codigo_sku' => $sku,
            'tipo_envase' => $presentacion['tipo_envase'] ?? null,
            'contenido' => $presentacion['contenido'],
            'unidad_medida' => $presentacion['unidad_medida'],
            'tipo_empaque' => $vendeEmpaque ? ($presentacion['tipo_empaque'] ?? null) : null,
            'unidades_por_empaque' => $vendeEmpaque ? ($presentacion['unidades_por_empaque'] ?? null) : null,
            'venta_por_unidad' => $vendeUnidad,
            'venta_por_empaque' => $vendeEmpaque,
            'precio_unitario' => $vendeUnidad ? ($presentacion['precio_unitario'] ?? null) : null,
            'precio_empaque' => $vendeEmpaque ? ($presentacion['precio_empaque'] ?? null) : null,
            'estado' => $productoActivo && (bool) ($presentacion['estado'] ?? true),
        ];
    }

    private function generarSku(string $prefijo, ?string $valorVariante, mixed $contenido, ?string $unidad): string
    {
        $partes = [$this->normalizarPrefijo($prefijo)];

        if ($valorVariante) {
            $partes[] = $this->abreviar($valorVariante);
        }

        $numero = rtrim(rtrim(number_format((float) $contenido, 2, '.', ''), '0'), '.');
        $numero = str_replace('.', 'P', $numero);
        $unidadNormalizada = Str::upper((string) $unidad);

        $partes[] = $numero.$unidadNormalizada;

        return implode('-', array_filter($partes));
    }

    private function normalizarPrefijo(string $valor): string
    {
        $valor = Str::upper(Str::ascii(trim($valor)));
        $valor = preg_replace('/[^A-Z0-9-]+/', '-', $valor) ?? '';
        $valor = preg_replace('/-+/', '-', $valor) ?? '';

        return trim($valor, '-');
    }

    private function abreviar(string $valor): string
    {
        $texto = Str::upper(Str::ascii($valor));
        $palabras = preg_split('/[^A-Z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (empty($palabras)) {
            return 'VAR';
        }

        return substr($palabras[0], 0, 3);
    }

    private function generarCodigoSugerido(): string
    {
        $siguiente = (Producto::max('id') ?? 0) + 1;

        return 'PRD'.str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
    }
}
