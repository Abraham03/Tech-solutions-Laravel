<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceStatusEnum;
use App\Enums\ServiceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
            'type' => ['required', new Enum(ServiceTypeEnum::class)],
            'provider' => 'required|string|max:100',
            'name' => 'required|string|max:150',
            'cost_mxn' => 'required|numeric|min:0',
            'price_mxn' => 'required|numeric|min:0',
            'billing_cycle' => 'sometimes|in:monthly,quarterly,annually,biennially,one-time',
            'expiration_date' => 'sometimes|date',
            'status' => ['sometimes', new Enum(ServiceStatusEnum::class)],

            // Solo para 'basketpro_subscription': con esto se crea la cuenta en BasketPro.
            // El formato fino (códigos, fuerza de la contraseña) lo valida BasketPro y su
            // mensaje llega al formulario tal cual.
            'basketpro' => ['required_if:type,basketpro_subscription', 'array'],
            'basketpro.account_code' => ['required_if:type,basketpro_subscription', 'string', 'max:30', 'unique:services,basketpro_tenant_code'],
            'basketpro.database_name' => ['required_if:type,basketpro_subscription', 'string', 'max:64'],
            // En Hostinger cada base tiene su propio usuario MySQL y es el único que la alcanza:
            // BasketPro se conecta con estos datos. Viajan a BasketPro y no se guardan aquí.
            'basketpro.database_username' => ['required_if:type,basketpro_subscription', 'string', 'max:80'],
            'basketpro.database_password' => ['required_if:type,basketpro_subscription', 'string', 'max:255'],
            'basketpro.league_code' => ['required_if:type,basketpro_subscription', 'string', 'max:30'],
            'basketpro.league_name' => ['required_if:type,basketpro_subscription', 'string', 'max:150'],
            'basketpro.admin_name' => ['required_if:type,basketpro_subscription', 'string', 'max:100'],
            'basketpro.admin_username' => ['required_if:type,basketpro_subscription', 'string', 'max:50'],
            'basketpro.admin_password' => ['required_if:type,basketpro_subscription', 'string', 'min:8'],
            'basketpro.plan_code' => ['required_if:type,basketpro_subscription', 'string', 'max:30'],
        ];
    }
}
