<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subquery) use ($search) {
                    $subquery
                        ->where('nombre', 'like', "%{$search}%")
                        ->orWhere('documento', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('mercado', 'like', "%{$search}%")
                        ->orWhere('distrito', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('estado', $request->input('status') === '1');
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('clients.index', compact('clientes'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validarFormulario($request);

        $validated['estado'] = true;

        $cliente = Cliente::create($validated);

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'El cliente fue registrado correctamente.');
    }

    public function show(Cliente $cliente): View
    {
        return view('clients.show', compact('cliente'));
    }

    public function edit(Cliente $cliente): View
    {
        return view('clients.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $this->validarFormulario($request);

        $cliente->update($validated);

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'El cliente fue actualizado correctamente.');
    }

    public function toggleStatus(Cliente $cliente): RedirectResponse
    {
        $cliente->update([
            'estado' => !$cliente->estado,
        ]);

        return back()->with(
            'success',
            $cliente->estado
                ? 'El cliente fue activado correctamente.'
                : 'El cliente fue desactivado correctamente.'
        );
    }

    private function validarFormulario(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:180'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:180'],
            'mercado' => ['nullable', 'string', 'max:150'],
            'puesto_referencia' => ['nullable', 'string', 'max:180'],
            'distrito' => ['nullable', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'correo.email' => 'Ingrese un correo electrónico válido.',
        ]);
    }
}
