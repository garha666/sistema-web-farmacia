<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Convention;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PosAuthorizationKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_set_a_pos_pin_and_use_it_to_authorize_affiliation_and_discounts(): void
    {
        $supervisor = User::factory()->quimico()->create();
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $key = '0843719205';

        $this->actingAs($cashier)->get(route('account.pos-authorization.edit'))->assertForbidden();
        $this->actingAs($supervisor)->put(route('account.pharmacist-pin.update'), [
            'current_password' => 'password', 'pharmacist_pin' => $key, 'pharmacist_pin_confirmation' => $key,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('account.pos-authorization.edit');

        $this->actingAs($cashier);
        $this->openCashRegister();
        $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);
        $this->post('/pos/cart', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $convention = Convention::factory()->create(['requires_membership' => true]);
        $payload = [
            'membership_rut' => '1-9', 'membership_name' => 'Cliente ficticio', 'membership_convention' => $convention->id,
            'supervisor_email' => $supervisor->email, 'supervisor_password' => 'password',
        ];
        $this->from('/pos/checkout')->post('/pos/checkout/membership', $payload)
            ->assertSessionHasErrorsIn('membership', 'supervisor_email')->assertSessionMissing('_old_input.supervisor_password');
        $this->post('/pos/checkout/membership', array_replace($payload, ['supervisor_password' => $key]))
            ->assertSessionHasNoErrors()->assertRedirectToRoute('pos.checkout');
        $this->get('/pos/checkout')->assertSee($convention->name)->assertSee(__('pos.authorization.registered'));
        $this->post('/pos/cart/'.$product->id.'/discount', ['line_type' => 'percent', 'line_value' => 5, 'line_reason' => 'Prueba'])
            ->assertSessionHasErrors('line_discount_supervisor');
        $this->post('/pos/cart/'.$product->id.'/discount', [
            'line_type' => 'percent', 'line_value' => 5, 'line_reason' => 'Prueba',
            'line_supervisor_email' => $supervisor->email, 'line_supervisor_password' => $key,
        ])->assertSessionHasNoErrors();
    }
}
