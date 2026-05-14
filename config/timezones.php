<?php

return [
  /*
    |--------------------------------------------------------------------------
    | Schedule Timezones
    |--------------------------------------------------------------------------
    |
    | Timezones offered in the Lead Receiving Schedule selector (Buyer form).
    | The order defined here is the order shown in the UI.
    |
    | Each entry: { value: <IANA timezone>, description?, prefix? }
    |   - value: IANA timezone id (used for storage & runtime tz conversion).
    |   - description (optional): friendly name appended after " — " in the
    |     label (e.g. "Eastern Time"). Omit for plain city labels.
    |   - prefix (optional): override the default "Continent/City" prefix
    |     (built from the value by replacing underscores with spaces). Use
    |     this for diacritics (Bogotá, São Paulo).
    |
    | The UTC offset is appended at render time, so labels stay accurate
    | across DST transitions without manual maintenance. UTC itself gets
    | no offset suffix (it's redundant).
    |
    */
  'schedule' => [
    ['value' => 'America/New_York', 'description' => 'Eastern Time'],
    ['value' => 'America/Chicago', 'description' => 'Central Time'],
    ['value' => 'America/Denver', 'description' => 'Mountain Time'],
    ['value' => 'America/Los_Angeles', 'description' => 'Pacific Time'],
    ['value' => 'UTC', 'description' => 'Coordinated Universal Time'],
    ['value' => 'Europe/London'],
    ['value' => 'Europe/Madrid'],
    ['value' => 'America/Mexico_City'],
    ['value' => 'America/Bogota', 'prefix' => 'America/Bogotá'],
    ['value' => 'America/Lima'],
    ['value' => 'America/Sao_Paulo', 'prefix' => 'America/São Paulo'],
  ],
];
