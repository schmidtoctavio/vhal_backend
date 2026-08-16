<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'slot_index' => [
                'required',
                'integer',
                'between:0,4',
            ],

            'name' => [
                'required',
                'string',
                'min:3',
                'max:16',

                Rule::unique(
                    'characters',
                    'name'
                ),
            ],

            'class_id' => [
                'required',
                'string',
                'max:50',

                Rule::exists(
                    'character_classes',
                    'id'
                )->where(
                    fn ($query) => (
                        $query->where(
                            'is_enabled',
                            true
                        )
                    )
                ),
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'slot_index.required' => (
                'El slot es obligatorio.'
            ),

            'slot_index.integer' => (
                'El slot no es válido.'
            ),

            'slot_index.between' => (
                'El slot debe estar entre 0 y 4.'
            ),

            'name.required' => (
                'Ingresá un nombre para el personaje.'
            ),

            'name.min' => (
                'El nombre debe tener al menos 3 caracteres.'
            ),

            'name.max' => (
                'El nombre no puede superar los 16 caracteres.'
            ),

            'name.unique' => (
                'Ese nombre ya está en uso.'
            ),

            'class_id.required' => (
                'Seleccioná una clase.'
            ),

            'class_id.exists' => (
                'La clase seleccionada no está disponible.'
            ),
        ];
    }
}