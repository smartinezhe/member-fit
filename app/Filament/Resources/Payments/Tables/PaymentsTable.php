<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Models\Plan;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tabla de pagos (RF-15, RN-09).
 *
 * Solo configura la interfaz. Qué cuenta como ingreso lo define el scope
 * Payment::countedAsIncome() (pagos de membresías no anuladas) y el rango de
 * fechas lo resuelve Payment::paidBetween(); ambos viven en el modelo y los
 * reutilizarán los indicadores y los reportes. El total de la columna de
 * valor se calcula sobre lo filtrado, y por usar la misma consulta siempre
 * coincide con la suma de las filas visibles.
 */
class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->countedAsIncome()
                ->with([
                    // Incluye socios eliminados lógicamente: su pago sigue contando.
                    'membership.member' => fn ($member) => $member->withTrashed(),
                    'membership.plan',
                ]))
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Fecha de pago')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('membership.member.full_name')
                    ->label('Socio')
                    ->placeholder('—'),
                TextColumn::make('membership.plan.name')
                    ->label('Plan'),
                TextColumn::make('method')
                    ->label('Método de pago'),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('COP')
                    ->sortable()
                    ->summarize(Sum::make('total')->label('Total')->money('COP')),
            ])
            ->filters([
                // Rango de fechas con extremos inclusivos; uno vacío deja ese lado abierto (RN-09).
                Filter::make('paid_between')
                    ->label('Fecha de pago')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->paidBetween(
                        filled($data['from'] ?? null) ? Carbon::parse($data['from']) : null,
                        filled($data['until'] ?? null) ? Carbon::parse($data['until']) : null,
                    )),
                SelectFilter::make('plan')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('membership', fn (Builder $membership) => $membership->where('plan_id', $data['value']))
                        : $query),
                SelectFilter::make('method')
                    ->label('Método de pago')
                    ->options(PaymentMethod::class),
            ])
            ->defaultSort('paid_at', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
