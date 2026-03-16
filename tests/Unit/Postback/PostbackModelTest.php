<?php

use App\Models\Platform;
use App\Models\Postback;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('getGeneratedUrlAttribute', function () {
    it('uses the public api_url domain when is_public is true', function () {
        config(['app.api_url' => 'https://public-api.example.com']);
        config(['app.url' => 'https://internal.example.com']);

        $postback = Postback::factory()->asPublic()->create(['param_mappings' => []]);

        expect($postback->generated_url)->toStartWith('https://public-api.example.com/v1/postback/fire/');
    });

    it('uses the internal app_url domain when is_public is false', function () {
        config(['app.api_url' => 'https://public-api.example.com']);
        config(['app.url' => 'https://internal.example.com']);

        $postback = Postback::factory()->asInternal()->create(['param_mappings' => []]);

        expect($postback->generated_url)->toStartWith('https://internal.example.com/v1/postback/fire/');
    });

    it('returns only the base path when param_mappings is empty', function () {
        $postback = Postback::factory()->create(['param_mappings' => []]);

        expect($postback->generated_url)->toBe(
            rtrim(config('app.api_url'), '/') . '/v1/postback/fire/' . $postback->uuid
        );
    });

    it('uses external platform token names as placeholders when platform is loaded', function () {
        $platform = Platform::factory()->withMappings([
            'Cost' => 'payout',
            'Callid' => 'click_id',
        ])->create();

        $postback = Postback::factory()->forPlatform($platform)->create([
            'param_mappings' => ['click_id' => 'click_id', 'payout' => 'payout'],
        ]);
        $postback->load('platform');

        expect($postback->generated_url)
            ->toContain('click_id={Callid}')
            ->toContain('payout={Cost}');
    });

    it('falls back to internal token names as placeholders when platform is not loaded', function () {
        $postback = Postback::factory()->create([
            'param_mappings' => ['click_id' => 'click_id', 'payout' => 'payout'],
        ]);
        // Do NOT load platform relation

        expect($postback->generated_url)
            ->toContain('click_id={click_id}')
            ->toContain('payout={payout}');
    });
});

describe('buildOutboundUrl', function () {
    it('maps inbound token values to destination params', function () {
        $postback = Postback::factory()->make([
            'base_url' => 'https://dest.example.com/cv?click_id=&payout=',
            'param_mappings' => ['click_id' => 'click_id', 'payout' => 'payout'],
        ]);

        $url = $postback->buildOutboundUrl(['click_id' => 'ABC', 'payout' => '9.99']);

        expect($url)->toBe('https://dest.example.com/cv?click_id=ABC&payout=9.99');
    });

    it('leaves unmapped params empty without breaking the url', function () {
        $postback = Postback::factory()->make([
            'base_url' => 'https://dest.example.com/cv?click_id=',
            'param_mappings' => ['click_id' => 'click_id'],
        ]);

        $url = $postback->buildOutboundUrl([]); // no values provided

        expect($url)->toContain('click_id=');
        expect($url)->not->toContain('{');
    });
});

describe('scopeActive', function () {
    it('returns only active postbacks', function () {
        Postback::factory()->create(['is_active' => true]);
        Postback::factory()->create(['is_active' => true]);
        Postback::factory()->inactive()->create();

        $results = Postback::query()->active()->get();

        expect($results)->toHaveCount(2);
        expect($results->every(fn ($p) => $p->is_active))->toBeTrue();
    });
});
