# Normalizacion de suscripciones tenant en produccion

Estado: operativo

## Objetivo

Evitar que el guard nuevo bloquee tenants activos por falta de registros en
`tenant_subscriptions` o `tenant_payments`.

## Auditoria

Ejecutar:

```bash
php artisan tenants:audit-subscriptions
```

Para un tenant especifico:

```bash
php artisan tenants:audit-subscriptions --tenant=1 --json
```

## Normalizar trial historico

Usar cuando un tenant activo tiene plan y vencimiento, pero no tiene
suscripcion/pago, y el acceso fue de prueba.

Primero dry-run:

```bash
php artisan tenants:normalize-trial 1 --starts=2026-06-11 --ends=2026-07-10 --created-by=1
```

Aplicar:

```bash
php artisan tenants:normalize-trial 1 --starts=2026-06-11 --ends=2026-07-10 --created-by=1 --apply
```

El comando crea:

- `tenant_subscriptions.status = active`;
- `tenant_subscriptions.trial_ends_at = ends`;
- `tenant_payments.status = paid`;
- `tenant_payments.amount = 0`;
- `tenant_payments.payment_method = trial`;
- actualiza `tenants.trial_ends_at` y `tenants.subscription_ends_at`.

## Tenant cancelado

Si un tenant esta `status = cancelled`, debe quedar `is_active = false`.

En Tinker:

```php
\App\Models\Tenant::find($id)->update(['is_active' => false]);
```

## Check final antes de deploy

En Tinker:

```php
$now = now();

\App\Models\Tenant::with(['subscriptions', 'payments'])
    ->where('status', 'active')
    ->where('is_active', true)
    ->get()
    ->filter(function ($t) use ($now) {
        $activeSub = $t->subscriptions
            ->first(fn ($s) => $s->status === 'active' && (!$s->ends_at || $s->ends_at->gte($now)));

        $paid = $t->payments
            ->first(fn ($p) => $p->status === 'paid' && (!$p->period_ends_at || $p->period_ends_at->gte($now)));

        $pending = $t->payments
            ->first(fn ($p) => $p->status === 'pending');

        return !$activeSub && !$paid && !$pending;
    })
    ->count();
```

Resultado esperado:

```php
0
```

## Resultado de produccion auditado

Antes del deploy del guard:

- tenant `1`: normalizado como trial historico `$0`, acceso OK;
- tenant `2`: `cancelled` e `is_active = false`, bloqueo administrativo;
- tenant `3`: acceso OK;
- tenant `4`: acceso OK;
- tenant `5`: facturacion limitada por pago pendiente;
- check final de activos sin suscripcion/pago/pending: `0`.

