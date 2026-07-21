# Graph Report - .  (2026-07-20)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 2421 nodes · 6335 edges · 294 communities (228 shown, 66 thin omitted)
- Extraction: 91% EXTRACTED · 9% INFERRED · 0% AMBIGUOUS · INFERRED: 569 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `a5e9bafb`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 22
- Community 23
- Community 24
- Community 25
- Community 26
- Community 27
- Community 28
- Community 29
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 123
- Community 187
- Community 196
- Community 211
- Community 213
- Community 214

## God Nodes (most connected - your core abstractions)
1. `Tenant` - 223 edges
2. `User` - 156 edges
3. `Customer` - 130 edges
4. `Animal` - 108 edges
5. `Controller` - 105 edges
6. `Appointment` - 105 edges
7. `Note` - 69 edges
8. `AppointmentService` - 64 edges
9. `CatalogItem` - 63 edges
10. `TestCase` - 59 edges

## Surprising Connections (you probably didn't know these)
- `CustomerAppointmentContext` --references--> `Customer`  [EXTRACTED]
  app/Data/CustomerAppointmentContext.php → app/Models/Customer.php
- `CustomerAppointmentContext` --references--> `CustomerPortalAccess`  [EXTRACTED]
  app/Data/CustomerAppointmentContext.php → app/Models/CustomerPortalAccess.php
- `CustomerAppointmentContext` --references--> `Tenant`  [EXTRACTED]
  app/Data/CustomerAppointmentContext.php → app/Models/Tenant.php
- `CustomerAppointmentContext` --references--> `User`  [EXTRACTED]
  app/Data/CustomerAppointmentContext.php → app/Models/User.php
- `DashboardController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Admin/DashboardController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (294 total, 66 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (11): AnimalFieldValue, AnimalTypeField, AppointmentScheduleLock, CustomerAccountSetting, CustomerTaxProfile, InvoiceItem, PriceHistory, TenantBillingProfile (+3 more)

### Community 2 - "Community 2"
Cohesion: 0.09
Nodes (11): NotificationController, AdminNotification, AppServiceProvider, BroadcastServiceProvider, DisabledPushGateway, FirebasePushGateway, Illuminate\Support\ServiceProvider, Kreait\Firebase\Contract\Messaging (+3 more)

### Community 3 - "Community 3"
Cohesion: 0.15
Nodes (10): AppointmentEventFactory, AppointmentFactory, AppointmentNotificationDeliveryFactory, AppointmentProposalFactory, AppointmentSettingFactory, DoctorScheduleFactory, PushDeviceFactory, ScheduleBlockFactory (+2 more)

### Community 4 - "Community 4"
Cohesion: 0.07
Nodes (13): ConfiguracionController, ReportesController, ClubController, PaymentMethodController, ActivationController, InvitationController, NewPasswordController, PasswordResetLinkController (+5 more)

### Community 5 - "Community 5"
Cohesion: 0.08
Nodes (4): AnimalController, AnimalController, Animal, MicrochipLetterPdfService

### Community 6 - "Community 6"
Cohesion: 0.08
Nodes (9): AppointmentController, CarbonImmutable, CancelTenantAppointmentRequest, ConfirmAppointmentRequest, FinishAppointmentRequest, ProposeAppointmentRequest, RejectAppointmentRequest, StoreManualAppointmentRequest (+1 more)

### Community 7 - "Community 7"
Cohesion: 0.14
Nodes (7): AnimalReportController, AnimalReport, AnimalReportImage, AnimalReportImageOptimizer, AnimalReportPdfService, RichTextSanitizer, DOMNode

### Community 9 - "Community 9"
Cohesion: 0.23
Nodes (6): AppointmentScheduleLockService, AppointmentService, AppointmentEventType, AppointmentStatus, CarbonImmutable, DateTimeInterface

### Community 10 - "Community 10"
Cohesion: 0.15
Nodes (5): AppointmentEvent, AppointmentNotificationService, CarbonImmutable, Illuminate\Database\Eloquent\Collection, NotificationDeliveryChannel

### Community 11 - "Community 11"
Cohesion: 0.09
Nodes (8): AuthServiceProvider, CreatesApplication, Illuminate\Foundation\Support\Providers\AuthServiceProvider, Illuminate\Foundation\Testing\DatabaseTransactions, Illuminate\Foundation\Testing\TestCase, ExampleTest, PublicAppStorePagesTest, TestCase

### Community 13 - "Community 13"
Cohesion: 0.07
Nodes (6): User, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens, Spatie\Permission\Traits\HasRoles, AdminTenantUsersTest

### Community 14 - "Community 14"
Cohesion: 0.15
Nodes (4): CustomerUserLink, AppointmentEmailTest, AppointmentEventType, CustomerAppointmentApiTest

### Community 15 - "Community 15"
Cohesion: 0.08
Nodes (7): AppointmentAvailabilityRequest, AppointmentIndexRequest, CancelAppointmentRequest, TenantAppointmentAvailabilityRequest, TenantAppointmentIndexRequest, UpdateBookableServiceRequest, Illuminate\Foundation\Http\FormRequest

### Community 16 - "Community 16"
Cohesion: 0.10
Nodes (10): AuditTenantSubscriptions, ExpireAppointmentProposals, NormalizeTenantTrial, CustomerAppointmentContext, Illuminate\Console\Command, Illuminate\Support\Collection, PHPUnit\Framework\TestCase, ExampleTest (+2 more)

### Community 18 - "Community 18"
Cohesion: 0.13
Nodes (3): CatalogItemController, CatalogItemController, CatalogItem

### Community 19 - "Community 19"
Cohesion: 0.16
Nodes (5): NotificationController, NotificationController, TenantNotification, AppointmentNotificationServiceTest, AppointmentEventType

### Community 20 - "Community 20"
Cohesion: 0.13
Nodes (4): PlanesController, Plan, StripePlanSyncService, StripeTenantCheckoutService

### Community 21 - "Community 21"
Cohesion: 0.12
Nodes (9): CheckTenantSubscription, EnsureApiTenantAccess, EnsureCustomerPortalAccess, EnsureTenantHasActivePlan, EnsureValidMobileAccessSession, EnsureValidWebAccessSession, RedirectIfAuthenticated, Closure (+1 more)

### Community 23 - "Community 23"
Cohesion: 0.16
Nodes (4): DashboardController, StripeWebhookController, TenantPayment, TenantSubscription

### Community 24 - "Community 24"
Cohesion: 0.16
Nodes (7): AppointmentSlot, AppointmentAvailabilityService, CarbonImmutable, CarbonImmutable, TenantAppointmentQueryService, Carbon\CarbonImmutable, Carbon\CarbonInterface

### Community 25 - "Community 25"
Cohesion: 0.15
Nodes (4): GenerateCustomerStatements, StatementController, CustomerStatement, CustomerStatementGenerator

### Community 26 - "Community 26"
Cohesion: 0.18
Nodes (5): AnimalClinicalMediaController, SyncController, Authenticate, Illuminate\Auth\Middleware\Authenticate, Illuminate\Http\Request

### Community 27 - "Community 27"
Cohesion: 0.11
Nodes (5): PushDeviceController, StorePushDeviceRequest, PushDevice, PushDeviceRegistrar, PushNotificationFoundationTest

### Community 28 - "Community 28"
Cohesion: 0.33
Nodes (3): AppointmentAvailabilityServiceTest, AppointmentStatus, CarbonImmutable

### Community 30 - "Community 30"
Cohesion: 0.13
Nodes (5): CustomerAppointmentController, BookableServiceResource, AppointmentProposal, CustomerAppointmentContextResolver, Illuminate\Database\Eloquent\Builder

### Community 31 - "Community 31"
Cohesion: 0.14
Nodes (5): DashboardController, TenantOnboardingStep, TenantOnboardingService, Illuminate\Http\RedirectResponse, TenantOnboardingStepTest

### Community 32 - "Community 32"
Cohesion: 0.13
Nodes (10): AppointmentEventResource, AppointmentProposalResource, AppointmentResource, AppointmentStatus, PushDeviceResource, TenantAppointmentEventResource, TenantAppointmentProposalResource, AppointmentStatus (+2 more)

### Community 33 - "Community 33"
Cohesion: 0.17
Nodes (9): ExpireAppointmentProposals, ProcessAppointmentEventNotifications, SendAppointmentEmail, SendAppointmentPush, Illuminate\Contracts\Queue\ShouldBeUnique, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Bus\Dispatchable, Illuminate\Queue\InteractsWithQueue (+1 more)

### Community 34 - "Community 34"
Cohesion: 0.14
Nodes (4): AppointmentSetting, DoctorSchedule, ScheduleBlock, AppointmentConfigurationService

### Community 35 - "Community 35"
Cohesion: 0.09
Nodes (22): alpinejs, autoprefixer, axios, driver.js, laravel-vite-plugin, dependencies, alpinejs, driver.js (+14 more)

### Community 36 - "Community 36"
Cohesion: 0.23
Nodes (3): MobileBootstrapController, Carbon, Carbon\Carbon

### Community 37 - "Community 37"
Cohesion: 0.15
Nodes (5): PaymentController, NotePayment, Payment, CustomerPaymentService, Illuminate\Database\Eloquent\Relations\Pivot

### Community 40 - "Community 40"
Cohesion: 0.15
Nodes (3): PaymentController, PaymentMethodController, PaymentMethod

### Community 41 - "Community 41"
Cohesion: 0.13
Nodes (4): PublicCustomerPaymentController, CustomerPaymentLink, CustomerStripePaymentProcessor, StripeCustomerPaymentService

### Community 45 - "Community 45"
Cohesion: 0.14
Nodes (4): PublicNotePaymentController, NotePaymentLink, StripeNotePaymentService, Stripe\StripeClient

### Community 47 - "Community 47"
Cohesion: 0.18
Nodes (3): ClubController, Club, Coggin

### Community 48 - "Community 48"
Cohesion: 0.15
Nodes (3): RadiologyController, RadiologyImage, RadiologyStudy

### Community 49 - "Community 49"
Cohesion: 0.13
Nodes (3): TenantHomeRouteResolver, TenantHomeRoutes, TenantMenuModules

### Community 50 - "Community 50"
Cohesion: 0.16
Nodes (3): AnimalTypeController, AnimalType, Illuminate\Database\Eloquent\SoftDeletes

### Community 52 - "Community 52"
Cohesion: 0.23
Nodes (3): VaccinationLetterController, VaccinationLetter, VaccinationLetterPdfService

### Community 53 - "Community 53"
Cohesion: 0.16
Nodes (3): FinalUserPatientAssignment, TenantPortalSetting, CustomerPortalAccessService

### Community 56 - "Community 56"
Cohesion: 0.18
Nodes (6): GdImage, LetterheadImageOptimizer, MicrochipImageOptimizer, GdImage, VeterinarianSignatureOptimizer, Illuminate\Http\UploadedFile

### Community 57 - "Community 57"
Cohesion: 0.14
Nodes (3): StoreDoctorScheduleRequest, StoreScheduleBlockRequest, UpdateAppointmentSettingsRequest

### Community 60 - "Community 60"
Cohesion: 0.29
Nodes (6): AppointmentEventMail, TenantActivationMail, TenantUserInvitationMail, Illuminate\Bus\Queueable, Illuminate\Mail\Mailable, Illuminate\Queue\SerializesModels

### Community 64 - "Community 64"
Cohesion: 0.27
Nodes (7): availableSteps(), hasBeenSeen(), initializeContextualTours(), markAsSeen(), startContextualTour(), storageKey(), tours

### Community 66 - "Community 66"
Cohesion: 0.22
Nodes (3): ProfileController, GloballyUniqueEmail, Illuminate\Contracts\Validation\ValidationRule

### Community 70 - "Community 70"
Cohesion: 0.18
Nodes (11): require, barryvdh/laravel-dompdf, guzzlehttp/guzzle, kreait/laravel-firebase, laravel/framework, laravel/sanctum, laravel/tinker, league/flysystem-aws-s3-v3 (+3 more)

### Community 76 - "Community 76"
Cohesion: 0.20
Nodes (9): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+1 more)

### Community 77 - "Community 77"
Cohesion: 0.20
Nodes (10): scripts, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan key:generate --ansi, @php artisan package:discover --ansi (+2 more)

### Community 85 - "Community 85"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit, spatie/laravel-ignition

### Community 86 - "Community 86"
Cohesion: 0.19
Nodes (6): InvalidPushTokenException, PermanentPushException, TransientPushException, AppointmentIdempotencyKey, AppointmentIdempotencyService, RuntimeException

### Community 89 - "Community 89"
Cohesion: 0.38
Nodes (3): DatabaseSeeder, RoleSeeder, Illuminate\Database\Seeder

### Community 91 - "Community 91"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 96 - "Community 96"
Cohesion: 0.33
Nodes (6): pestphp/pest-plugin, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 104 - "Community 104"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 105 - "Community 105"
Cohesion: 0.40
Nodes (5): dev-master, extra, branch-alias, laravel, dont-discover

### Community 111 - "Community 111"
Cohesion: 0.50
Nodes (3): client.customers.partials.activation-invite, client.customers.partials.statement-modal, client.customers.partials.statement-modal-script

### Community 112 - "Community 112"
Cohesion: 0.50
Nodes (3): client.customers.partials.activation-invite, client.customers.partials.statement-modal, client.customers.partials.statement-modal-script

### Community 123 - "Community 123"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

## Knowledge Gaps
- **62 isolated node(s):** `name`, `type`, `description`, `framework`, `laravel` (+57 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **66 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Tenant` connect `Community 1` to `Community 0`, `Community 2`, `Community 3`, `Community 4`, `Community 9`, `Community 11`, `Community 13`, `Community 14`, `Community 16`, `Community 19`, `Community 20`, `Community 21`, `Community 22`, `Community 23`, `Community 24`, `Community 26`, `Community 27`, `Community 28`, `Community 29`, `Community 31`, `Community 34`, `Community 42`, `Community 43`, `Community 44`, `Community 45`, `Community 50`, `Community 51`, `Community 54`, `Community 59`, `Community 60`, `Community 62`, `Community 63`, `Community 65`, `Community 69`, `Community 71`, `Community 200`, `Community 72`, `Community 73`, `Community 78`, `Community 79`, `Community 80`, `Community 83`, `Community 84`, `Community 86`, `Community 90`, `Community 93`, `Community 94`, `Community 95`, `Community 97`, `Community 98`, `Community 99`, `Community 106`, `Community 113`?**
  _High betweenness centrality (0.125) - this node is a cross-community bridge._
- **Why does `Controller` connect `Community 4` to `Community 0`, `Community 2`, `Community 5`, `Community 6`, `Community 7`, `Community 8`, `Community 12`, `Community 17`, `Community 18`, `Community 19`, `Community 20`, `Community 21`, `Community 22`, `Community 23`, `Community 25`, `Community 26`, `Community 27`, `Community 30`, `Community 31`, `Community 36`, `Community 37`, `Community 38`, `Community 40`, `Community 41`, `Community 44`, `Community 45`, `Community 47`, `Community 48`, `Community 50`, `Community 52`, `Community 54`, `Community 57`, `Community 58`, `Community 61`, `Community 66`, `Community 68`, `Community 81`, `Community 82`, `Community 100`, `Community 108`?**
  _High betweenness centrality (0.056) - this node is a cross-community bridge._
- **Why does `Appointment` connect `Community 22` to `Community 0`, `Community 1`, `Community 2`, `Community 3`, `Community 6`, `Community 9`, `Community 10`, `Community 14`, `Community 19`, `Community 21`, `Community 24`, `Community 28`, `Community 29`, `Community 30`, `Community 42`, `Community 43`, `Community 50`, `Community 59`, `Community 65`, `Community 200`, `Community 98`?**
  _High betweenness centrality (0.055) - this node is a cross-community bridge._
- **Are the 45 inferred relationships involving `Tenant` (e.g. with `.handle()` and `.index()`) actually correct?**
  _`Tenant` has 45 INFERRED edges - model-reasoned connections that need verification._
- **Are the 29 inferred relationships involving `User` (e.g. with `.store()` and `.show()`) actually correct?**
  _`User` has 29 INFERRED edges - model-reasoned connections that need verification._
- **Are the 34 inferred relationships involving `Customer` (e.g. with `.handle()` and `.customers()`) actually correct?**
  _`Customer` has 34 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `type`, `description` to the rest of the system?**
  _62 weakly-connected nodes found - possible documentation gaps or missing edges._