<?php

namespace App\Http\Requests;

use App\Enums\AlasanTolak;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TolakCatatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // Otorisasi per catatan dilakukan di controller.
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', Rule::enum(AlasanTolak::class)],
            'catatan' => ['nullable', 'required_if:alasan,lainnya', 'string', 'max:500'],
            'tab' => ['nullable', 'in:ditandai,normal,diperiksa'],
        ];
    }

    public function attributes(): array
    {
        return ['alasan' => 'alasan penolakan', 'catatan' => 'catatan untuk biller'];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Pilih alasan penolakan agar biller tahu apa yang harus diperbaiki.',
            'catatan.required_if' => 'Tulis catatan bila alasan penolakan "Lainnya".',
        ];
    }
}
