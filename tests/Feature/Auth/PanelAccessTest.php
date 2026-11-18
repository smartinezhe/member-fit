<?php

use App\Models\User;

it('lets an active user into the panel', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

it('blocks an inactive user from the panel', function () {
    $user = User::factory()->admin()->inactive()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});
