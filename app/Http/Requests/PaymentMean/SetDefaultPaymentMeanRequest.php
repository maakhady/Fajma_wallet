<?php

namespace App\Http\Requests\PaymentMean;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\PaymentMean;

class SetDefaultPaymentMeanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Vérifier que l'utilisateur est propriétaire du moyen de paiement ou admin
        $user = Auth::user();
        $paymentMeanId = $this->route('id');
        $paymentMean = PaymentMean::find($paymentMeanId);

        return $user && ($user->role === 'admin' || ($paymentMean && $paymentMean->user_id === $user->id));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        // Pas de champs à valider pour cette requête
        return [];
    }
}
