<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class PermissionMatrixPicker extends Field
{
    protected string $view = 'filament.forms.components.permission-matrix-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
    }
}
