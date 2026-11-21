<?php

use App\Models\User;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    // Los componentes Livewire no pasan por el middleware del panel,
    // así que se indica manualmente cuál es el panel actual.
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // El código del 2FA se envía por una notificación encolada (DP-15).
    // Con el fake no hay cola ni red: se captura la notificación y se lee su código.
    Notification::fake();
});

/**
 * Devuelve el código de 6 dígitos que se envió por correo al usuario.
 *
 * Falla la prueba si no se envió ninguna notificación de verificación.
 */
function sentTwoFactorCode(User $user): string
{
    $code = null;

    Notification::assertSentTo($user, VerifyEmailAuthentication::class, function (VerifyEmailAuthentication $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('sends a code and does not authenticate after valid credentials (RF-03)', function () {
    $user = User::factory()->admin()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertNotSet('userUndertakingMultiFactorAuthentication', null);

    $this->assertGuest();
    Notification::assertSentTo($user, VerifyEmailAuthentication::class);
});

it('authenticates with the correct code (RF-03)', function () {
    $user = User::factory()->admin()->create();

    $login = Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $login->set('data.multiFactor.email_code.code', sentTwoFactorCode($user))
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong code (RF-03)', function () {
    $user = User::factory()->admin()->create();

    $login = Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $code = sentTwoFactorCode($user);
    $wrongCode = $code === '000000' ? '111111' : '000000';

    $login->set('data.multiFactor.email_code.code', $wrongCode)
        ->call('authenticate')
        ->assertHasErrors(['data.multiFactor.email_code.code']);

    $this->assertGuest();
});

it('rejects an expired code after 4 minutes (RF-03)', function () {
    $user = User::factory()->admin()->create();

    $login = Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $code = sentTwoFactorCode($user);

    // El código vence a los 4 minutos (valor por defecto de Filament, DP-02).
    $this->travelTo(now()->addMinutes(5));

    $login->set('data.multiFactor.email_code.code', $code)
        ->call('authenticate')
        ->assertHasErrors(['data.multiFactor.email_code.code']);

    $this->assertGuest();
});

it('does not send a code to an inactive user with 2FA enabled (RF-01)', function () {
    $user = User::factory()->admin()->inactive()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
    Notification::assertNothingSent();
});

it('forces a user without 2FA to set it up before using the panel (RF-04)', function () {
    $user = User::factory()->admin()->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirectContains('multi-factor-authentication/set-up');
});
