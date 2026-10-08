<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    public function index(): View
    {
        $categorias = Categoria::withCount('productos')
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get();

        return view('categories.index', compact('categorias'));
    }

    public function create(): View
    {
        $codigoSugerido = $this->generarCodigoSugerido();

        return view('categories.create', compact('codigoSugerido'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'codigo' => strtoupper(trim((string) $request->input('codigo'))),
        ]);

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:10',
                'regex:/^CAT\d{3,6}$/',
                'unique:categorias,codigo',
            ],
            'nombre' => [
                'required',
                'string',
                'max:100',
                'unique:categorias,nombre',
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'boolean'],
        ], [
            'codigo.regex' => 'El código debe usar el formato CAT001.',
        ]);

        Categoria::create($validated);

        return redirect()
            ->route('categorias.index')
            ->with('success', 'La categoría fue registrada correctamente.');
    }

    public function show(Categoria $categoria): View
    {
        $categoria->loadCount('productos');

        $productos = $categoria->productos()
            ->withCount('presentaciones')
            ->orderBy('nombre')
            ->get();

        return view('categories.show', compact('categoria', 'productos'));
    }

    public function edit(Categoria $categoria): View
    {
        return view('categories.edit', compact('categoria'));
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        $request->merge([
            'codigo' => strtoupper(trim((string) $request->input('codigo'))),
        ]);

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:10',
                'regex:/^CAT\d{3,6}$/',
                Rule::unique('categorias', 'codigo')->ignore($categoria->id),
            ],
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre')->ignore($categoria->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'boolean'],
        ], [
            'codigo.regex' => 'El código debe usar el formato CAT001.',
        ]);

        $categoria->update($validated);

        return redirect()
            ->route('categorias.show', $categoria)
            ->with('success', 'La categoría fue actualizada correctamente.');
    }

    public function toggleStatus(Categoria $categoria): RedirectResponse
    {
        $categoria->update([
            'estado' => !$categoria->estado,
        ]);

        return back()->with(
            'success',
            $categoria->estado
                ? 'La categoría fue activada correctamente.'
                : 'La categoría fue desactivada correctamente.'
        );
    }

    private function generarCodigoSugerido(): string
    {
        $mayor = Categoria::query()
            ->whereNotNull('codigo')
            ->pluck('codigo')
            ->map(function ($codigo) {
                return preg_match('/^CAT(\d+)$/', $codigo, $coincidencia)
                    ? (int) $coincidencia[1]
                    : 0;
            })
            ->max() ?? 0;

        return 'CAT'.str_pad((string) ($mayor + 1), 3, '0', STR_PAD_LEFT);
    }
}
