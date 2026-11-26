<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('profile'))->assertRedirect('/login');
        $this->post(route('profile.update'), ['kulliyyah' => 'KICT'])->assertRedirect('/login');
    }

    public function test_a_student_can_save_their_kulliyyah(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), ['kulliyyah' => 'KICT'])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success', 'Profile updated.');

        $this->assertSame('KICT', $user->fresh()->kulliyyah);
        $this->get(route('profile'))->assertOk()->assertSee('value="KICT" selected', false);
    }

    public function test_only_a_listed_kulliyyah_is_accepted(): void
    {
        $user = User::factory()->create(['kulliyyah' => 'KOE']);
        $this->actingAs($user);

        $this->post(route('profile.update'), ['kulliyyah' => 'HACKER'])
            ->assertSessionHasErrors(['kulliyyah' => 'Please select a kulliyyah from the list.']);
        $this->post(route('profile.update'), [])
            ->assertSessionHasErrors(['kulliyyah' => 'Please select a kulliyyah from the list.']);

        $this->assertSame('KOE', $user->fresh()->kulliyyah);
    }

    public function test_an_unlisted_kulliyyah_shows_the_placeholder_not_the_first_option(): void
    {
        $this->actingAs(User::factory()->create(['kulliyyah' => 'ISTAC']))
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('<option value="" disabled selected>', false)
            ->assertDontSee('value="KICT" selected', false);
    }

    public function test_the_profile_form_cannot_change_any_other_field(): void
    {
        $user = User::factory()->create(['name' => 'Aisyah', 'email' => 'aisyah@example.com']);

        $this->actingAs($user)->post(route('profile.update'), [
            'kulliyyah' => 'KOS',
            'is_admin' => 1,
            'name' => 'Someone Else',
            'email' => 'other@example.com',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('KOS', $user->kulliyyah);
        $this->assertFalse($user->is_admin);
        $this->assertSame('Aisyah', $user->name);
        $this->assertSame('aisyah@example.com', $user->email);
    }
}
