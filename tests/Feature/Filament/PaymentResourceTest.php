<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// --- Autorización por rol (RF-15, RNF-02) ---

it('shows payments to admins and returns 403 to everyone else (RF-15)', function (string $state, int $status) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin/payments')
        ->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'receptionist' => ['receptionist', 403],
    'trainer' => ['trainer', 403],
]);

it('has no create or edit pages (RN-04)', function () {
    $payment = Payment::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/payments/create')->assertNotFound();
    $this->get("/admin/payments/{$payment->getKey()}/edit")->assertNotFound();
});

// --- Qué se lista (RN-09, opción A) ---

it('lists only the payments that count as income (RN-09)', function () {
    $counted = Payment::factory()->create();
    $cancelled = Payment::factory()->create(['membership_id' => Membership::factory()->cancelled()]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$counted])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

// --- Filtros (RF-15) ---

it('filters payments by method (RF-15)', function () {
    $cash = Payment::factory()->create(['method' => PaymentMethod::Cash]);
    $transfer = Payment::factory()->create(['method' => PaymentMethod::Transfer]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->filterTable('method', PaymentMethod::Transfer)
        ->assertCanSeeTableRecords([$transfer])
        ->assertCanNotSeeTableRecords([$cash]);
});

it('filters payments by plan (RF-15)', function () {
    $monthly = Plan::factory()->create();
    $annual = Plan::factory()->create();
    $monthlyPayment = Payment::factory()->create(['membership_id' => Membership::factory()->for($monthly)]);
    $annualPayment = Payment::factory()->create(['membership_id' => Membership::factory()->for($annual)]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->filterTable('plan', $monthly)
        ->assertCanSeeTableRecords([$monthlyPayment])
        ->assertCanNotSeeTableRecords([$annualPayment]);
});

it('filters payments by an inclusive date range (RF-15, RN-09)', function () {
    $before = Payment::factory()->create(['paid_at' => '2026-10-31 23:59:59']);
    $first = Payment::factory()->create(['paid_at' => '2026-11-01 00:00:00']);
    $last = Payment::factory()->create(['paid_at' => '2026-11-30 23:59:59']);
    $after = Payment::factory()->create(['paid_at' => '2026-12-01 00:00:00']);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->filterTable('paid_between', ['from' => '2026-11-01', 'until' => '2026-11-30'])
        ->assertCanSeeTableRecords([$first, $last])
        ->assertCanNotSeeTableRecords([$before, $after]);
});

// --- Total de lo filtrado (RF-15, RN-09) ---

it('totals all the filtered payments, not just the current page (RF-15, RN-09)', function () {
    // 30 pagos de 1.000 y 2 de 5.000: más filas que las 25 de una página.
    Payment::factory()->count(30)->create(['amount' => 1000, 'method' => PaymentMethod::Cash]);
    Payment::factory()->count(2)->create(['amount' => 5000, 'method' => PaymentMethod::Transfer]);
    // El pago de una membresía anulada no cuenta como ingreso (RN-09).
    Payment::factory()->create([
        'amount' => 99999,
        'method' => PaymentMethod::Cash,
        'membership_id' => Membership::factory()->cancelled(),
    ]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->assertTableColumnSummarySet('amount', 'total', 40000);
});

it('totals only the payments left by the active filters (RF-15)', function () {
    Payment::factory()->count(3)->create(['amount' => 1000, 'method' => PaymentMethod::Cash]);
    Payment::factory()->count(2)->create(['amount' => 5000, 'method' => PaymentMethod::Transfer]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->filterTable('method', PaymentMethod::Transfer)
        ->assertTableColumnSummarySet('amount', 'total', 10000);
});
