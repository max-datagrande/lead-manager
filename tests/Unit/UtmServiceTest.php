<?php

use App\Services\UtmService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
  $this->service = new UtmService();
});

// ──────────────────────────────────────────────────────────────────
// getClickData()
// ──────────────────────────────────────────────────────────────────

describe('getClickData', function () {
  it('returns null platform when no click ID is found', function () {
    $result = $this->service->getClickData(['foo' => 'bar', 'utm_source' => 'google']);

    expect($result['click_id'])->toBeNull();
    expect($result['platform'])->toBeNull();
  });

  it('returns correct click ID and platform for Google Ads gclid', function () {
    $result = $this->service->getClickData(['gclid' => 'EAIaIQobChMI']);

    expect($result['click_id'])->toBe('EAIaIQobChMI');
    expect($result['platform'])->toBe('Google Ads');
  });

  it('returns correct click ID and platform for Meta Ads fbclid', function () {
    $result = $this->service->getClickData(['fbclid' => 'IwAR3xabc123']);

    expect($result['click_id'])->toBe('IwAR3xabc123');
    expect($result['platform'])->toBe('Meta Ads (Facebook/Instagram)');
  });

  it('returns correct click ID and platform for TikTok ttclid', function () {
    $result = $this->service->getClickData(['ttclid' => 'E.C.P.abc']);

    expect($result['click_id'])->toBe('E.C.P.abc');
    expect($result['platform'])->toBe('TikTok Ads');
  });

  it('returns first match when multiple click IDs are present', function () {
    $result = $this->service->getClickData([
      'gclid' => 'google_click',
      'fbclid' => 'fb_click',
    ]);

    expect($result['click_id'])->toBe('google_click');
    expect($result['platform'])->toBe('Google Ads');
  });

  it('ignores empty click ID values', function () {
    $result = $this->service->getClickData(['gclid' => '', 'fbclid' => 'valid_fb_click']);

    expect($result['click_id'])->toBe('valid_fb_click');
    expect($result['platform'])->toBe('Meta Ads (Facebook/Instagram)');
  });
});

// ──────────────────────────────────────────────────────────────────
// getSourceData()
// ──────────────────────────────────────────────────────────────────

describe('getSourceData', function () {
  it('returns direct channel when no utm_source, no referrer, no click ID', function () {
    $result = $this->service->getSourceData([]);

    expect($result['source'])->toBeNull();
    expect($result['channel'])->toBe('direct');
    expect($result['platform'])->toBeNull();
  });

  it('returns organic channel with utm_source', function () {
    $result = $this->service->getSourceData(['utm_source' => 'newsletter']);

    expect($result['source'])->toBe('newsletter');
    expect($result['channel'])->toBe('organic');
  });

  it('returns organic channel with referrer when no utm_source', function () {
    $result = $this->service->getSourceData([], 'https://www.google.com/search?q=test');

    expect($result['source'])->toBe('google');
    expect($result['channel'])->toBe('organic');
  });

  it('returns ads channel when click ID is present', function () {
    $result = $this->service->getSourceData(['gclid' => 'abc123']);

    expect($result['channel'])->toBe('ads');
    expect($result['platform'])->toBe('Google Ads');
    expect($result['click_id'])->toBe('abc123');
  });

  it('overrides organic to ads when click ID coexists with utm_source', function () {
    $result = $this->service->getSourceData([
      'utm_source' => 'google',
      'gclid' => 'abc123',
    ]);

    expect($result['source'])->toBe('google');
    expect($result['channel'])->toBe('ads');
    expect($result['platform'])->toBe('Google Ads');
  });
});

// ──────────────────────────────────────────────────────────────────
// analyzeTrafficData()
// ──────────────────────────────────────────────────────────────────

describe('analyzeTrafficData', function () {
  it('normalizes query param keys to lowercase', function () {
    $result = $this->service->analyzeTrafficData(null, [
      'UTM_SOURCE' => 'Google',
      'UTM_MEDIUM' => 'cpc',
    ]);

    expect($result['utm_source'])->toBe('Google');
    expect($result['utm_medium'])->toBe('cpc');
  });

  it('extracts campaign ID from multiple param names with priority', function () {
    // campaign_id has highest priority
    $result = $this->service->analyzeTrafficData(null, [
      'campaign_id' => 'camp_1',
      'utm_id' => 'camp_2',
      'cid' => 'camp_3',
    ]);

    expect($result['utm_campaign_id'])->toBe('camp_1');
  });

  it('falls back to utm_id when campaign_id is missing', function () {
    $result = $this->service->analyzeTrafficData(null, ['utm_id' => 'camp_2']);

    expect($result['utm_campaign_id'])->toBe('camp_2');
  });
});

// ──────────────────────────────────────────────────────────────────
// getTrafficStats() — Sprint 5: BUG-3 fix
// ──────────────────────────────────────────────────────────────────

describe('getTrafficStats', function () {
  it('queries using utm_medium column without SQL error', function () {
    // This test verifies the method uses correct column names after rename.
    // It should not throw a QueryException for missing 'traffic_medium' column.
    $result = $this->service->getTrafficStats();

    expect($result)->toHaveKeys(['by_source', 'by_medium', 'by_country', 'total_visits', 'unique_visitors']);
  });
});

// ──────────────────────────────────────────────────────────────────
// getTrafficLogsByCampaign() — Sprint 5: BUG-3 fix
// ──────────────────────────────────────────────────────────────────

describe('getTrafficLogsByCampaign', function () {
  it('filters by utm_campaign_id without SQL error', function () {
    $query = $this->service->getTrafficLogsByCampaign([
      'utm_campaign_id' => 'test_campaign_123',
    ]);

    // Should return a builder without throwing QueryException
    expect($query)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
  });

  it('filters by campaign_code', function () {
    $query = $this->service->getTrafficLogsByCampaign([
      'campaign_code' => 'native',
    ]);

    expect($query)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
  });
});
