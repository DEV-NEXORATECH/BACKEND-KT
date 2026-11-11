<?php

namespace Tests\Feature;

use App\Models\Master\BeneficiaryPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryPartnerCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_key_is_generated_and_manual_code_stays_editable(): void
    {
        $partner = BeneficiaryPartner::create([
            'code' => 'IMP - 001',
            'name' => 'Partner Test',
            'type' => 'implementing_partner',
            'is_active' => true,
        ]);

        $this->assertSame('BP-' . str_pad((string) $partner->id, 6, '0', STR_PAD_LEFT), $partner->partner_key);

        $partner->update(['code' => 'IMP - 009']);

        $this->assertSame('IMP - 009', $partner->fresh()->code);
        $this->assertSame('BP-' . str_pad((string) $partner->id, 6, '0', STR_PAD_LEFT), $partner->fresh()->partner_key);
    }
}
