<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\ParticipantImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantController extends Controller
{
    public function index(): View
    {
        return view('participants', ['participants' => Participant::orderBy('name')->orderBy('id')->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:participants,email']);
        Participant::create($data);

        return to_route('participants.index')->with('status', 'Participante cargado. Ya podés usar su enlace personal.');
    }

    public function import(Request $request, ParticipantImport $import): RedirectResponse
    {
        $request->validate(['csv' => 'required|file|max:2048']);
        $count = $import->import($request->file('csv'));

        return to_route('participants.index')->with('status', "Se importaron {$count} participantes. Los enlaces existentes se conservaron.");
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['nombre', 'email', 'uid', 'formulario'], ',', '"', '');
            foreach (Participant::orderBy('id')->cursor() as $participant) {
                $values = [$participant->name, $participant->email, $participant->uid, route('formulario', ['uid' => $participant->uid])];
                $values = array_map(fn (string $value): string => preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $values);
                fputcsv($output, $values, ',', '"', '');
            }
            fclose($output);
        }, 'participantes.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
