<?php

declare(strict_types=1);

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edición de socios (RF-09). Solo eliminación lógica y restauración, y
 * únicamente para el administrador según MemberPolicy. No hay eliminación
 * física: un socio con membresías o asistencias no se borra (RN-11).
 */
class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
