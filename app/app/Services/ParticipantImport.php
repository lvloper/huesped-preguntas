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
            $columns = [];
            foreach (['uid' => ['id', 'uid'], 'name' => ['user', 'nombre', 'name'], 'email' => ['mail', 'email']] as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $column = array_search($alias, $headers, true);
                    if ($column !== false) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }
            if (count($columns) !== 3) {
                throw ValidationException::withMessages(['csv' => 'El CSV debe tener las columnas id, user y mail.']);
            }

            $participants = [];
            $emails = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }

                $data = ['uid' => trim($row[$columns['uid']] ?? ''), 'name' => trim($row[$columns['name']] ?? ''), 'email' => strtolower(trim($row[$columns['email']] ?? ''))];
                $validator = Validator::make($data, ['uid' => 'required|'.Participant::UID_RULES, 'name' => 'required|string|max:150', 'email' => 'required|email|max:255']);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['csv' => "Fila {$line}: revisá el ID, el usuario y el mail. El ID admite hasta 40 letras, números, guiones o guiones bajos."]);
                }
                if (isset($participants[$data['uid']])) {
                    throw ValidationException::withMessages(['csv' => "Fila {$line}: el ID está repetido en el archivo."]);
                }
                if (isset($emails[$data['email']])) {
                    throw ValidationException::withMessages(['csv' => "Fila {$line}: el email está repetido en el archivo."]);
                }
                $participants[$data['uid']] = $data;
                $emails[$data['email']] = true;
            }

            if ($participants === []) {
                throw ValidationException::withMessages(['csv' => 'El archivo no contiene participantes.']);
            }

            DB::transaction(function () use ($participants): void {
                foreach ($participants as $data) {
                    if (Participant::where('email', $data['email'])->where('uid', '!=', $data['uid'])->exists()) {
                        throw ValidationException::withMessages(['csv' => "El mail del ID {$data['uid']} ya está asignado a otro ID."]);
                    }
                    Participant::updateOrCreate(['uid' => $data['uid']], ['name' => $data['name'], 'email' => $data['email']]);
                }
            });

            return count($participants);
        } finally {
            fclose($handle);
        }
    }
}
