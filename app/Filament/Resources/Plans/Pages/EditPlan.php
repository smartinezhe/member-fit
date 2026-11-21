<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    // Sin acciones de cabecera: un plan no se elimina, solo se desactiva (RN-12).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
