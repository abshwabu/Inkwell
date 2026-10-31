<?php

namespace App\Http\Requests;

use App\Models\Series;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $series = $this->route('series');
        if (! $series instanceof Series) {
            $series = Series::find($series);
        }

        return $series && $this->user() && $this->user()->can('update', $series);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cover_image_path' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:ongoing,completed,hiatus'],
        ];
    }
}
