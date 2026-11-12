<?php

namespace App\Http\Requests;

use App\Enums\HasilTusbug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TusbugLaksanakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('laksanakan', $this->route('tusbug'));
    }

    public function rules(): array
    {
        $tusbug = $this->route('tusbug');
        $ada = fn (string $j) => $tusbug->lampiran()->where('jenis', $j)->exists();

        return [
            'hasil' => ['required', Rule::enum(HasilTusbug::class)],
            'keterangan' => ['nullable', 'required_if:hasil,ditunda,tidak_dapat_diakses', 'string', 'max:500'],
            'foto_sebelum' => [$ada('sebelum') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_sesudah' => [$ada('sesudah') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_sebelum_diambil_at' => ['nullable', 'date'],
            'foto_sesudah_diambil_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function attributes(): array
    {
        return ['hasil' => 'hasil pelaksanaan', 'foto_sebelum' => 'foto sebelum', 'foto_sesudah' => 'foto sesudah'];
    }

    public function messages(): array
    {
        return [
            'keterangan.required_if' => 'Jelaskan kenapa pemutusan ditunda atau tidak dapat dilakukan.',
            'foto_sebelum.required' => 'Ambil foto meter sebelum tindakan.',
            'foto_sesudah.required' => 'Ambil foto meter sesudah tindakan.',
        ];
    }
}
