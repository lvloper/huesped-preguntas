<?php

namespace App\Services;

use App\Models\Participant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParticipantImport
{
    public function import(UploadedFile $file): int
    {
        $handle = fopen($file->getRealPath(), 'r');

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['csv' => 'El archivo está vacío.']);
            }

            $delimiter = count(str_getcsv($firstLine, ';', '"', '')) > count(str_getcsv($firstLine, ',', '"', '')) ? ';' : ',';
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            $headers = array_map(fn (?string $header): string => strtolower(trim(ltrim($header ?? '', "\xEF\xBB\xBF"))), $headers);
            $nameColumn = array_search('nombre', $headers, true);
            if ($nameColumn === false) {
                $nameColumn = array_search('name', $headers, true);
            }
            $emailColumn = array_search('email', $headers, true);
            if ($nameColumn === false || $emailColumn === false) {
                throw ValidationException::withMessages(['csv' => 'El CSV debe tener las columnas nombre y email.']);
            }

            $participants = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }

                $data = ['name' => trim($row[$nameColumn] ?? ''), 'email' => strtolower(trim($row[$emailColumn] ?? ''))];
                $validator = Validator::make($data, ['name' => 'required|string|max:150', 'email' => 'required|email|max:255']);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['csv' => "Fila {$line}: revisá el nombre y el email."]);
                }
                if (isset($participants[$data['email']])) {
                    throw ValidationException::withMessages(['csv' => "Fila {$line}: el email está repetido en el archivo."]);
                }
                $participants[$data['email']] = $data;
            }

            if ($participants === []) {
                throw ValidationException::withMessages(['csv' => 'El archivo no contiene participantes.']);
            }

            DB::transaction(function () use ($participants): void {
                foreach ($participants as $data) {
                    Participant::updateOrCreate(['email' => $data['email']], ['name' => $data['name']]);
                }
            });

            return count($participants);
        } finally {
            fclose($handle);
        }
    }
}
