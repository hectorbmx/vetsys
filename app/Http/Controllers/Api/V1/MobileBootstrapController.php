<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalFieldValue;
use App\Models\AnimalType;
use App\Models\AnimalTypeField;
use App\Models\CatalogItem;
use App\Models\Club;
use App\Models\Customer;
use App\Models\Note;
use App\Models\NoteDetail;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MobileBootstrapController extends Controller
{
    public function __invoke(Request $request, AuthController $authController)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $user = $request->user()->load('tenant.plan');
        $tenant = $user->tenant;
        $since = isset($data['since']) ? Carbon::parse($data['since']) : null;

        return response()->json([
            'server_time' => now()->toISOString(),
            'since' => $since?->toISOString(),
            'user' => $authController->serializeUser($user),
            'catalogs' => [
                'animal_types' => $this->animalTypes($tenant->id, $since),
                'animal_type_fields' => $this->animalTypeFields($tenant->id, $since),
                'clubs' => $this->clubs($tenant->id, $since),
                'payment_methods' => $this->paymentMethods($tenant->id, $since),
                'catalog_items' => $this->catalogItems($tenant->id, $since),
            ],
            'customers' => $this->customers($tenant->id, $since),
            'animals' => $this->animals($tenant->id, $since),
            'animal_field_values' => $this->animalFieldValues($tenant->id, $since),
            'notes' => $this->notes($tenant->id, $since),
            'note_details' => $this->noteDetails($tenant->id, $since),
        ]);
    }

    private function animalTypes(int $tenantId, ?Carbon $since)
    {
        return AnimalType::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('name')
            ->get()
            ->map(fn (AnimalType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'description' => $type->description,
                'is_active' => $type->is_active,
                'created_at' => $type->created_at?->toISOString(),
                'updated_at' => $type->updated_at?->toISOString(),
                'deleted_at' => $type->deleted_at?->toISOString(),
            ]);
    }

    private function animalTypeFields(int $tenantId, ?Carbon $since)
    {
        return AnimalTypeField::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('animal_type_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (AnimalTypeField $field) => [
                'id' => $field->id,
                'animal_type_id' => $field->animal_type_id,
                'label' => $field->label,
                'slug' => $field->slug,
                'field_type' => $field->field_type,
                'options' => $field->options_json,
                'is_required' => $field->is_required,
                'is_active' => $field->is_active,
                'sort_order' => $field->sort_order,
                'help_text' => $field->help_text,
                'created_at' => $field->created_at?->toISOString(),
                'updated_at' => $field->updated_at?->toISOString(),
                'deleted_at' => $field->deleted_at?->toISOString(),
            ]);
    }

    private function clubs(int $tenantId, ?Carbon $since)
    {
        return Club::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('name')
            ->get()
            ->map(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'description' => $club->description,
                'is_active' => $club->is_active,
                'created_at' => $club->created_at?->toISOString(),
                'updated_at' => $club->updated_at?->toISOString(),
                'deleted_at' => $club->deleted_at?->toISOString(),
            ]);
    }

    private function paymentMethods(int $tenantId, ?Carbon $since)
    {
        return PaymentMethod::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'slug' => $method->slug,
                'description' => $method->description,
                'is_active' => $method->is_active,
                'created_at' => $method->created_at?->toISOString(),
                'updated_at' => $method->updated_at?->toISOString(),
                'deleted_at' => $method->deleted_at?->toISOString(),
            ]);
    }

    private function catalogItems(int $tenantId, ?Carbon $since)
    {
        return CatalogItem::withTrashed()
            ->with(['inventory', 'priceHistories' => fn ($query) => $query->whereNull('end_date')])
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'type' => $item->type,
                'description' => $item->description,
                'tax_percentage' => $item->tax_percentage,
                'has_inventory' => $item->has_inventory,
                'is_active' => $item->is_active,
                'current_price' => $item->current_price,
                'stock_actual' => $item->inventory?->stock_actual,
                'stock_minimo' => $item->inventory?->stock_minimo,
                'allow_negative_stock' => $item->inventory?->allow_negative_stock ?? false,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
                'deleted_at' => $item->deleted_at?->toISOString(),
            ]);
    }

    private function customers(int $tenantId, ?Carbon $since)
    {
        return Customer::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'client_uuid' => $customer->client_uuid,
                'name' => $customer->name,
                'last_name' => $customer->last_name,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'secondary_phone' => $customer->secondary_phone,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'status' => $customer->status,
                'synced_from_mobile' => $customer->synced_from_mobile,
                'created_at' => $customer->created_at?->toISOString(),
                'updated_at' => $customer->updated_at?->toISOString(),
                'deleted_at' => $customer->deleted_at?->toISOString(),
            ]);
    }

    private function animals(int $tenantId, ?Carbon $since)
    {
        return Animal::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('id')
            ->get()
            ->map(fn (Animal $animal) => [
                'id' => $animal->id,
                'client_uuid' => $animal->client_uuid,
                'customer_id' => $animal->customer_id,
                'club_id' => $animal->club_id,
                'animal_type_id' => $animal->animal_type_id,
                'name' => $animal->name,
                'photo_path' => $animal->photo_path,
                'sex' => $animal->sex,
                'birthdate' => $animal->birthdate?->toDateString(),
                'color' => $animal->color,
                'weight' => $animal->weight,
                'microchip' => $animal->microchip,
                'status' => $animal->status,
                'notes' => $animal->notes,
                'synced_from_mobile' => $animal->synced_from_mobile,
                'created_at' => $animal->created_at?->toISOString(),
                'updated_at' => $animal->updated_at?->toISOString(),
                'deleted_at' => $animal->deleted_at?->toISOString(),
            ]);
    }

    private function animalFieldValues(int $tenantId, ?Carbon $since)
    {
        return AnimalFieldValue::with('field')
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>=', $since))
            ->orderBy('animal_id')
            ->orderBy('animal_type_field_id')
            ->get()
            ->map(fn (AnimalFieldValue $value) => [
                'id' => $value->id,
                'animal_id' => $value->animal_id,
                'animal_type_field_id' => $value->animal_type_field_id,
                'value' => $value->value,
                'file_path' => $value->file_path,
                'created_at' => $value->created_at?->toISOString(),
                'updated_at' => $value->updated_at?->toISOString(),
            ]);
    }

    private function notes(int $tenantId, ?Carbon $since)
    {
        return Note::withTrashed()
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $this->changedSince($query, $since))
            ->orderBy('id')
            ->get()
            ->map(fn (Note $note) => [
                'id' => $note->id,
                'client_uuid' => $note->client_uuid,
                'customer_id' => $note->customer_id,
                'folio' => $note->folio,
                'total' => $note->total,
                'amount_paid' => $note->amount_paid,
                'balance' => $note->balance,
                'status' => $note->status,
                'date_at' => $note->date_at?->toDateString(),
                'synced_from_mobile' => $note->synced_from_mobile,
                'created_at' => $note->created_at?->toISOString(),
                'updated_at' => $note->updated_at?->toISOString(),
                'deleted_at' => $note->deleted_at?->toISOString(),
            ]);
    }

    private function noteDetails(int $tenantId, ?Carbon $since)
    {
        return NoteDetail::with(['catalogItem', 'animal'])
            ->where('tenant_id', $tenantId)
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>=', $since))
            ->orderBy('note_id')
            ->orderBy('id')
            ->get()
            ->map(fn (NoteDetail $detail) => [
                'id' => $detail->id,
                'note_id' => $detail->note_id,
                'catalog_item_id' => $detail->catalog_item_id,
                'catalog_item_name' => $detail->catalogItem?->name,
                'animal_id' => $detail->animal_id,
                'animal_name' => $detail->animal?->name,
                'quantity' => $detail->quantity,
                'price_at_sale' => $detail->price_at_sale,
                'tax_at_sale' => $detail->tax_at_sale,
                'subtotal' => $detail->subtotal,
                'created_at' => $detail->created_at?->toISOString(),
                'updated_at' => $detail->updated_at?->toISOString(),
            ]);
    }

    private function changedSince(Builder $query, Carbon $since): Builder
    {
        return $query->where(function (Builder $query) use ($since) {
            $query->where('updated_at', '>=', $since)
                ->orWhere('deleted_at', '>=', $since);
        });
    }
}
