<?php

namespace App\Services;

use App\Mail\TenantUserInvitationMail;
use App\Models\Animal;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerUserLink;
use App\Models\FinalUserPatientAssignment;
use App\Models\TenantPortalSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class CustomerPortalAccessService
{
    public const VISIBILITY_FIELDS = [
        'show_profile',
        'show_history',
        'show_notes',
        'show_services',
        'show_products',
        'show_files',
        'show_videos',
        'show_radiology',
        'show_statement',
        'show_vaccines',
        'show_appointments',
    ];

    public function activate(Customer $customer, User $actor): array
    {
        $customer->loadMissing(['tenant', 'animals']);

        if (!$customer->email) {
            throw new RuntimeException('El cliente necesita un correo para activar acceso al portal/app.');
        }

        if ($customer->tenant_id !== $actor->tenant_id) {
            throw new RuntimeException('No puedes activar acceso para un cliente de otro tenant.');
        }

        $plainInvitationToken = null;
        $activationCode = null;
        $createdUser = false;

        $user = DB::transaction(function () use ($customer, $actor, &$plainInvitationToken, &$activationCode, &$createdUser) {
            TenantPortalSetting::firstOrCreate(
                ['tenant_id' => $customer->tenant_id],
                [
                    'is_portal_enabled' => true,
                    'is_mobile_access_enabled' => true,
                    'access_mode' => 'free',
                    'default_access_status' => 'active',
                    'requires_manual_activation' => true,
                    'currency' => 'MXN',
                    'created_by' => $actor->id,
                ]
            );

            $user = User::where('email', $customer->email)->first();

            if ($user && (int) $user->tenant_id !== (int) $customer->tenant_id) {
                throw new RuntimeException('Ya existe un usuario con ese correo en otro tenant.');
            }

            if ($user && !$user->hasRole('customer')) {
                throw new RuntimeException('Ya existe un usuario interno con ese correo. Usa un correo diferente para el cliente.');
            }

            Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

            if (!$user) {
                $activationCode = (string) random_int(100000, 999999);
                $plainInvitationToken = Str::random(64);
                $createdUser = true;

                $user = User::create([
                    'tenant_id' => $customer->tenant_id,
                    'name' => $customer->full_name ?: $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'password' => Hash::make(Str::random(32)),
                    'is_active' => false,
                    'created_by' => $actor->id,
                    'invitation_token' => User::activationCodeHash($activationCode),
                    'invitation_link_token' => hash('sha256', $plainInvitationToken),
                    'invitation_expires_at' => now()->addDays(7),
                ]);
            } elseif (!$user->is_active && !$user->invitation_accepted_at) {
                $activationCode = (string) random_int(100000, 999999);
                $plainInvitationToken = Str::random(64);

                $user->update([
                    'name' => $customer->full_name ?: $customer->name,
                    'phone' => $customer->phone,
                    'invitation_token' => User::activationCodeHash($activationCode),
                    'invitation_link_token' => hash('sha256', $plainInvitationToken),
                    'invitation_expires_at' => now()->addDays(7),
                ]);
            }

            if (!$user->hasRole('customer')) {
                $user->assignRole('customer');
            }

            CustomerUserLink::updateOrCreate(
                [
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                ],
                [
                    'relationship' => 'owner',
                    'is_primary' => true,
                    'created_by' => $actor->id,
                    'revoked_at' => null,
                ]
            );

            CustomerPortalAccess::updateOrCreate(
                [
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                    'billing_mode' => 'free',
                    'activated_by' => $actor->id,
                    'activated_at' => now(),
                    'access_starts_at' => now(),
                    'access_ends_at' => null,
                    'trial_ends_at' => null,
                    'revoked_at' => null,
                    'revoked_by' => null,
                ]
            );

            $customer->animals()
                ->where('status', 'active')
                ->get()
                ->each(function ($animal) use ($customer, $user, $actor) {
                    FinalUserPatientAssignment::updateOrCreate(
                        [
                            'tenant_id' => $customer->tenant_id,
                            'user_id' => $user->id,
                            'animal_id' => $animal->id,
                        ],
                        [
                            'customer_id' => $customer->id,
                            'assigned_by' => $actor->id,
                            'assigned_at' => now(),
                            'revoked_at' => null,
                        ]
                    );

                    AnimalPortalVisibilitySetting::updateOrCreate(
                        [
                            'tenant_id' => $customer->tenant_id,
                            'user_id' => $user->id,
                            'animal_id' => $animal->id,
                        ],
                        [
                            'customer_id' => $customer->id,
                            'show_profile' => true,
                            'show_history' => true,
                            'show_notes' => true,
                            'show_services' => true,
                            'show_products' => true,
                            'show_files' => true,
                            'show_videos' => true,
                            'show_radiology' => true,
                            'show_statement' => true,
                            'show_vaccines' => true,
                            'show_appointments' => true,
                            'updated_by' => $actor->id,
                        ]
                    );
                });

            return $user;
        });

        $mailSent = false;
        $invitationUrl = null;

        if ($plainInvitationToken) {
            $invitationUrl = route('invitation.accept', $plainInvitationToken);

            try {
                Mail::to($user->email)->send(
                    new TenantUserInvitationMail($user, $customer->tenant, $invitationUrl, $activationCode)
                );
                $mailSent = true;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'user' => $user,
            'created_user' => $createdUser,
            'mail_sent' => $mailSent,
            'invitation_url' => $invitationUrl,
            'activation_code' => $activationCode,
        ];
    }

    public function suspend(Customer $customer, User $actor): void
    {
        if ($customer->tenant_id !== $actor->tenant_id) {
            throw new RuntimeException('No puedes suspender acceso para un cliente de otro tenant.');
        }

        DB::transaction(function () use ($customer, $actor) {
            CustomerPortalAccess::where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'suspended',
                    'revoked_at' => now(),
                    'revoked_by' => $actor->id,
                    'updated_at' => now(),
                ]);
        });
    }

    public function updateAnimalVisibility(Customer $customer, int $animalId, User $actor, array $data): void
    {
        if ($customer->tenant_id !== $actor->tenant_id) {
            throw new RuntimeException('No puedes actualizar acceso para un cliente de otro tenant.');
        }

        $animal = $customer->animals()
            ->where('tenant_id', $customer->tenant_id)
            ->findOrFail($animalId);

        $portalUserIds = $customer->portalAccesses()
            ->where('status', 'active')
            ->pluck('user_id');

        if ($portalUserIds->isEmpty()) {
            throw new RuntimeException('Primero activa el acceso app/web del cliente.');
        }

        DB::transaction(function () use ($customer, $animal, $actor, $data, $portalUserIds) {
            foreach ($portalUserIds as $userId) {
                $assignment = FinalUserPatientAssignment::firstOrNew([
                    'tenant_id' => $customer->tenant_id,
                    'user_id' => $userId,
                    'animal_id' => $animal->id,
                ]);

                $assignment->fill([
                    'customer_id' => $customer->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => $assignment->assigned_at ?: now(),
                    'revoked_at' => ($data['is_shared'] ?? false) ? null : now(),
                ])->save();

                $visibilityPayload = [
                    'customer_id' => $customer->id,
                    'updated_by' => $actor->id,
                ];

                foreach (self::VISIBILITY_FIELDS as $field) {
                    $visibilityPayload[$field] = (bool) ($data[$field] ?? false);
                }

                if (!($data['is_shared'] ?? false)) {
                    foreach (self::VISIBILITY_FIELDS as $field) {
                        $visibilityPayload[$field] = false;
                    }
                }

                AnimalPortalVisibilitySetting::updateOrCreate(
                    [
                        'tenant_id' => $customer->tenant_id,
                        'user_id' => $userId,
                        'animal_id' => $animal->id,
                    ],
                    $visibilityPayload
                );
            }
        });
    }

    public function toggleAnimalVisibility(Animal $animal, User $actor): bool
    {
        if ($animal->tenant_id !== $actor->tenant_id) {
            throw new RuntimeException('No puedes actualizar un paciente de otro tenant.');
        }

        $customer = $animal->customer;

        if (! $customer) {
            throw new RuntimeException('El paciente no tiene un cliente propietario.');
        }

        $portalUserIds = $customer->portalAccesses()
            ->where('status', 'active')
            ->pluck('user_id');

        if ($portalUserIds->isEmpty()) {
            throw new RuntimeException('Primero activa el acceso app/web del cliente.');
        }

        $isCurrentlyShared = FinalUserPatientAssignment::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('animal_id', $animal->id)
            ->whereIn('user_id', $portalUserIds)
            ->whereNull('revoked_at')
            ->exists();
        $shouldShare = ! $isCurrentlyShared;

        DB::transaction(function () use ($customer, $animal, $actor, $portalUserIds, $shouldShare) {
            foreach ($portalUserIds as $userId) {
                $assignment = FinalUserPatientAssignment::firstOrNew([
                    'tenant_id' => $customer->tenant_id,
                    'user_id' => $userId,
                    'animal_id' => $animal->id,
                ]);

                $assignment->fill([
                    'customer_id' => $customer->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => $assignment->assigned_at ?: now(),
                    'revoked_at' => $shouldShare ? null : now(),
                ])->save();

                if (! $shouldShare) {
                    continue;
                }

                $visibility = AnimalPortalVisibilitySetting::firstOrNew([
                    'tenant_id' => $customer->tenant_id,
                    'user_id' => $userId,
                    'animal_id' => $animal->id,
                ]);

                if (! $visibility->exists) {
                    foreach (self::VISIBILITY_FIELDS as $field) {
                        $visibility->{$field} = true;
                    }
                }

                $visibility->fill([
                    'customer_id' => $customer->id,
                    'updated_by' => $actor->id,
                ])->save();
            }
        });

        return $shouldShare;
    }
}
