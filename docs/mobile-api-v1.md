# Mobile API v1

Base path: `/api/v1`

Authentication: send `Authorization: Bearer <token>` for every protected request.

The mobile app is tenant-only. `super-admin` users are rejected by the API.

## Offline Contract

Records created offline by the app should include `client_uuid`.

The backend uses `client_uuid` to avoid duplicates when the app retries sync:

- `customers.client_uuid`
- `animals.client_uuid`
- `notes.client_uuid`
- `payments.client_uuid`

Suggested local SQLite fields:

```txt
local_id
server_id
client_uuid
sync_status
sync_error
created_at
updated_at
last_synced_at
```

Suggested `sync_status` values:

```txt
synced
pending_create
pending_update
pending_delete
error
```

## Auth

### POST `/auth/login`

```json
{
  "email": "user@example.com",
  "password": "secret",
  "device_name": "iphone-hector"
}
```

Returns:

```json
{
  "token_type": "Bearer",
  "token": "...",
  "user": {
    "id": 1,
    "name": "Demo",
    "email": "demo@example.com",
    "tenant": {},
    "roles": [],
    "permissions": []
  }
}
```

### GET `/auth/me`

Returns the authenticated user, tenant, roles and permissions.

### POST `/auth/logout`

Revokes the current Sanctum token.

## Bootstrap / Pull

### GET `/mobile/bootstrap`

Initial data load.

### GET `/mobile/bootstrap?since=2026-06-05T00:00:00Z`

Incremental load. Returns server changes since `updated_at` or `deleted_at`.

Includes:

- `user`
- `catalogs.animal_types`
- `catalogs.animal_type_fields`
- `catalogs.clubs`
- `catalogs.payment_methods`
- `catalogs.catalog_items`
- `customers`
- `animals`
- `animal_field_values`
- `notes`
- `note_details`

Save `server_time` locally as the next pull cursor.

## Customers

### GET `/customers`

Query params:

```txt
since
q
status=active|inactive
per_page
```

### POST `/customers`

```json
{
  "client_uuid": "2fb10f6c-7384-420d-972f-a6b660755dae",
  "name": "Juan",
  "last_name": "Perez",
  "email": "juan@example.com",
  "phone": "5551234567",
  "secondary_phone": null,
  "address": "Direccion",
  "notes": null,
  "status": "active"
}
```

If the same `client_uuid` was already synced, the API returns the existing record with `idempotent: true`.

### PUT/PATCH `/customers/{id}`

Partial updates are allowed.

## Animals

### GET `/animals`

Query params:

```txt
since
q
customer_id
status=active|inactive|deceased|transferred
per_page
```

### POST `/animals`

```json
{
  "client_uuid": "844d12bb-9f91-4c6e-b01c-762789768248",
  "customer_id": 1,
  "club_id": null,
  "animal_type_id": 1,
  "name": "Firulais",
  "sex": "unknown",
  "birthdate": null,
  "color": null,
  "weight": null,
  "microchip": null,
  "notes": null,
  "status": "active"
}
```

For batch sync, `customer_client_uuid` may be sent instead of `customer_id`.

## Catalog Items

### GET `/catalog-items`

Query params:

```txt
since
q
type=product|service
active=true|false
per_page
```

Returns active price as `current_price` and inventory values when available.
Inventoried products also include `allow_negative_stock`, which indicates whether
a note may consume more units than the current stock.

## Notes

### GET `/notes`

Query params:

```txt
since
customer_id
status=PENDIENTE|PAGADA|CANCELADA
per_page
```

### POST `/notes`

```json
{
  "client_uuid": "f0a3803a-eae5-4edc-a70c-b237ff7c7d13",
  "customer_id": 1,
  "date_at": "2026-06-05",
  "animal_ids": [1],
  "items": [
    {
      "id": 1,
      "quantity": 1,
      "price": 350,
      "tax_percentage": 0
    }
  ],
  "operation_type": "credito",
  "amount_received": 0,
  "payment_method_id": null
}
```

The backend assigns the real `folio`.

For batch sync, send `customer_client_uuid` and `animal_client_uuids` when the app created related records offline in the same batch.

## Payments

### POST `/payments`

Creates a manual payment and applies it FIFO to pending notes.

```json
{
  "client_uuid": "8dd0d8bb-cb4a-48fd-b471-d8600c461601",
  "customer_id": 1,
  "payment_method_id": 1,
  "amount": 500,
  "reference": "Transferencia"
}
```

### GET `/customers/{customer}/payments/preview?amount=500`

Returns the FIFO distribution without saving anything.

## Stripe Payment Links

### POST `/notes/{note}/payment-links`

Creates a public payment link for a synced note with balance.

```json
{
  "payment_method_id": null,
  "expires_in_hours": 24
}
```

Returns `public_url` for the app to share with the customer.

## Batch Sync

### POST `/sync/push`

```json
{
  "customers": [],
  "animals": [],
  "notes": [],
  "payments": []
}
```

The backend processes in this order:

```txt
customers -> animals -> notes -> payments
```

Each item returns:

```json
{
  "status": "synced",
  "client_uuid": "...",
  "idempotent": false,
  "data": {},
  "http_status": 201
}
```

Failed items return:

```json
{
  "status": "error",
  "client_uuid": "...",
  "message": "Validacion fallida.",
  "errors": {},
  "http_status": 422
}
```

The app should keep failed records as `error` or `pending_*` locally and retry after the user fixes the data.
