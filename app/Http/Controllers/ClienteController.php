<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $validated = $this->validarFormulario($request, $cliente);

        $cliente->update($validated);

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'El cliente fue actualizado correctamente.');
    }

    public function toggleStatus(Cliente $cliente): RedirectResponse
    {
        $cliente->update([
            'estado' => ! $cliente->estado,
        ]);

        return back()->with(
            'success',
            $cliente->estado
                ? 'El cliente fue activado correctamente.'
                : 'El cliente fue desactivado correctamente.'
        );
    }

    private function validarFormulario(Request $request, ?Cliente $cliente = null): array
    {
        if (is_string($request->input('documento'))) {
            $request->merge(['documento' => strtoupper(trim($request->input('documento'))) ?: null]);
        }
        $telefono = $request->input('telefono');
        if (is_string($telefono) && preg_match('/\A(?:\+51)?[0-9 ()-]+\z/', $telefono)) {
            $internacional = str_starts_with($telefono, '+51');
            $telefono = preg_replace('/\D/', '', $internacional ? substr($telefono, 3) : $telefono);
            if ($internacional && strlen($telefono) === 8 && str_starts_with($telefono, '1')) {
                $telefono = '0'.$telefono;
            }
            $request->merge(['telefono' => $telefono]);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:180', "regex:~\A(?=.*\\p{L})(?!.* {2})[\\p{L}\\p{M}\\p{N} .&'’()/-]+\\z~u"],
            'documento' => ['nullable', 'string', 'regex:/\A(?:[0-9]{8}|(?:10|15|17|20)[0-9]{9}|(?![0-9]{11}\z)[A-Z0-9]{9,12})\z/', Rule::unique('clientes', 'documento')->ignore($cliente?->id)],
            'telefono' => ['nullable', 'string', 'regex:/\A(?:9[0-9]{8}|01[0-9]{7})\z/'],
            'correo' => ['nullable', 'email:rfc', 'max:180'],
            'mercado' => ['nullable', 'string', 'max:150'],
            'puesto_referencia' => ['nullable', 'string', 'max:180'],
            'distrito' => ['nullable', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'correo.email' => 'Ingrese un correo electrónico válido.',
            'nombre.regex' => 'El nombre debe contener letras y no puede tener espacios dobles ni caracteres no permitidos.',
            'documento.regex' => 'Ingrese un DNI de 8 dígitos, un RUC de 11 dígitos que comience con 10, 15, 17 o 20, o un CE alfanumérico de 9 a 12 caracteres.',
            'telefono.regex' => 'Ingrese un móvil de 9 dígitos que comience con 9 o un fijo de Lima con 01 y 7 dígitos. Puede anteponer +51.',
        ]);
    }
}
