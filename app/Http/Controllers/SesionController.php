<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$datos, 'active' => true], $request->boolean('recordarme'))) {
            throw ValidationException::withMessages([
                'email' => 'No encontramos ese usuario o la clave no coincide.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->rutaInicio()));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
