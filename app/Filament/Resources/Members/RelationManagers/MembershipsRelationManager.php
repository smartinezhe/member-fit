<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\RelationManagers;

use App\Actions\CancelMembership;
use App\Actions\RenewMembership;
use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Historial de membresías del socio y registro de nuevas (RF-12, RF-13).
 *
 * Solo configura la interfaz: las reglas de fechas, valor y plan viven en la
 * Action RenewMembership (RN-02, RN-03, RN-12). No hay editar, eliminar ni
 * asociar: las fechas las calcula el sistema y una membresía errónea se anula
 * (RN-08, Día 5). Se muestra solo a quien MembershipPolicy::viewAny permite
 * (administrador y recepcionista): el historial incluye valores y métodos de
 * pago, que el entrenador no puede ver (RF-23).
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Membresías';
    }

    /**
     * Por defecto Filament deja los gestores en solo lectura en la página de
     * ver (la ficha). Aquí se necesita la acción «Registrar membresía» en la
     * ficha, que es donde trabaja la recepción.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plan', 'payment']))
            ->columns([
                TextColumn::make('plan.name')
                    ->label('Plan'),
                TextColumn::make('starts_on')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('payment.amount')
                    ->label('Valor')
                    ->money('COP'),
                TextColumn::make('payment.method')
                    ->label('Método de pago'),
                // Estado de esta membresía (RF-12): etiqueta, color e icono vienen del enum.
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->headerActions([
                $this->registerMembershipAction(),
            ])
            ->recordActions([
                $this->cancelMembershipAction(),
            ])
            ->defaultSort('starts_on', 'desc')
            ->defaultPaginationPageOption(25);
    }

    /**
     * Acción «Registrar membresía» (RF-13): delega en RenewMembership.
     *
     * El formulario ya impide los casos habituales (solo ofrece planes activos
     * y exige un valor mayor que 0). El catch cubre las condiciones de carrera,
     * por ejemplo un plan desactivado mientras el modal estaba abierto.
     */
    private function registerMembershipAction(): Action
    {
        return Action::make('registerMembership')
            ->label('Registrar membresía')
            ->icon('heroicon-m-plus')
            ->modalHeading('Registrar membresía')
            ->modalSubmitActionLabel('Registrar')
            ->visible(fn (): bool => Gate::allows('create', Membership::class))
            ->schema([
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->native(false)
                    ->live()
                    // RN-03: el valor por defecto es el precio vigente del plan.
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $price = Plan::query()->whereKey($state)->value('price');

                        $set('amount', $price);
                    }),
                TextInput::make('amount')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(9999999999.99)
                    ->prefix('$')
                    ->suffix('COP'),
                Select::make('method')
                    ->label('Método de pago')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, Action $action, RelationManager $livewire): void {
                // Defensa en profundidad: ocultar el botón no impide la llamada.
                Gate::authorize('create', Membership::class);

                $user = Auth::user();
                $member = $livewire->getOwnerRecord();

                abort_unless($user instanceof User && $member instanceof Member, 403);

                try {
                    // Un Select con opciones de enum entrega el caso ya convertido;
                    // se acepta también la cadena por si el valor llega sin hidratar.
                    $method = $data['method'] instanceof PaymentMethod
                        ? $data['method']
                        : PaymentMethod::from($data['method']);

                    app(RenewMembership::class)->handle(
                        $member,
                        Plan::query()->findOrFail($data['plan_id']),
                        $user,
                        $method,
                        $data['amount'],
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo registrar la membresía')
                        ->body((string) collect($exception->errors())->flatten()->first())
                        ->send();

                    // Mantiene el modal abierto para corregir y reintentar.
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title('Membresía registrada')
                    ->send();
            });
    }

    /**
     * Acción «Anular» (RF-14, RN-08): solo el administrador, solo sobre la
     * membresía más reciente no anulada, con motivo obligatorio. La regla de
     * fondo vive en CancelMembership; el botón se oculta para no ofrecerlo
     * donde no aplica.
     */
    private function cancelMembershipAction(): Action
    {
        return Action::make('cancelMembership')
            ->label('Anular')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->authorize('cancel')
            ->visible(fn (Membership $record): bool => $record->isLatestNotCancelled())
            ->modalHeading('Anular membresía')
            ->modalDescription('La membresía dejará de contar como vigente y su pago se excluirá de los ingresos. Las asistencias ya registradas se conservan. Esta acción no se puede deshacer.')
            ->modalSubmitActionLabel('Anular')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->minLength(CancelMembership::MIN_REASON_LENGTH)
                    ->maxLength(CancelMembership::MAX_REASON_LENGTH)
                    ->rows(3),
            ])
            ->action(function (array $data, Membership $record, Action $action): void {
                // Defensa en profundidad: ocultar el botón no impide la llamada.
                Gate::authorize('cancel', $record);

                try {
                    app(CancelMembership::class)->handle($record, $data['reason']);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo anular la membresía')
                        ->body((string) collect($exception->errors())->flatten()->first())
                        ->send();

                    // Mantiene el modal abierto para corregir y reintentar.
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title('Membresía anulada')
                    ->send();
            });
    }
}
