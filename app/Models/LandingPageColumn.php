<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageColumn extends Model
{
  public const SOURCE_FIELD = 'field';
  public const SOURCE_TRAFFIC = 'traffic';

  /**
   * Traffic columns derived from the GeoIP lookup.
   * Get the `Geo ` prefix in their human label.
   */
  public const GEO_IP_KEYS = ['ip_address', 'country_code', 'state', 'city', 'postal_code'];

  /**
   * Tokens that should render uppercased when they appear as a word in a label.
   */
  private const LABEL_ACRONYMS = ['ip', 'os', 'utm', 'url', 'id'];

  protected $fillable = ['landing_page_id', 'source', 'reference'];

  public function landingPage(): BelongsTo
  {
    return $this->belongsTo(LandingPage::class);
  }

  public function field(): BelongsTo
  {
    return $this->belongsTo(Field::class, 'reference');
  }

  /**
   * Build a human label for a traffic column key.
   * Applies title casing, uppercases known acronyms (IP, OS, UTM, URL, ID),
   * and prefixes "Geo " on columns derived from the GeoIP lookup.
   */
  public static function trafficLabel(string $key): string
  {
    $label = ucwords(str_replace('_', ' ', $key));

    foreach (self::LABEL_ACRONYMS as $acronym) {
      $label = preg_replace('/\b' . ucfirst($acronym) . '\b/', strtoupper($acronym), $label);
    }

    if (in_array($key, self::GEO_IP_KEYS, true)) {
      $label = 'Geo ' . $label;
    }

    return $label;
  }
}
