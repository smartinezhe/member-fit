<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de pagos (RF-15). Sin acciones de cabecera: no se crean pagos a mano.
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
