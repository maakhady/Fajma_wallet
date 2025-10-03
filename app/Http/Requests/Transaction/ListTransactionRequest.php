<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class ListTransactionRequest extends FormRequest
{ 
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:5,100',
            'card_id' => 'sometimes|integer|exists:cards,id',
            'type' => 'sometimes|exists:transaction_types,name',
            'status' => 'sometimes|exists:payment_status,name',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d|after_or_equal:date_from',
            'amount_min' => 'sometimes|numeric|min:0',
            'amount_max' => 'sometimes|numeric|gt:amount_min',
            'provider_id' => 'sometimes|integer|exists:providers,id',
            'sort_by' => 'sometimes|in:transaction_date,amount,created_at',
            'sort_dir' => 'sometimes|in:asc,desc',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'date_to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début',
            'amount_max.gt' => 'Le montant maximum doit être supérieur au montant minimum',
        ];
    }
}