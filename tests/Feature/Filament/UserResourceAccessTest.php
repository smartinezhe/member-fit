<?php

use App\Models\User;

it('shows the users list to admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/users')
        ->assertSuccessful();
});

it('returns 403 on the users resource for non-admin roles', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/admin/users')
        ->assertForbidden();
})->with(['receptionist', 'trainer']);
