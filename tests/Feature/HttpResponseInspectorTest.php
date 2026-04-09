<?php

use App\Support\HttpResponseInspector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

// ─── detectError (HTTP-level) ────────────────────────────────────────────────

it('detects 500 server error', function () {
  Http::fake(['*' => Http::response('Internal Server Error', 500)]);
  $response = Http::get('https://example.com');

  $result = HttpResponseInspector::detectError($response);

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toContain('500');
});

it('detects invalid JSON (HTML response)', function () {
  Http::fake(['*' => Http::response('<html><body>Error</body></html>', 200)]);
  $response = Http::get('https://example.com');

  $result = HttpResponseInspector::detectError($response);

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toContain('Invalid JSON');
});

it('passes valid JSON 200 response', function () {
  Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
  $response = Http::get('https://example.com');

  $result = HttpResponseInspector::detectError($response);

  expect($result['is_error'])->toBeFalse();
});

it('passes empty body as non-error', function () {
  Http::fake(['*' => Http::response('', 200)]);
  $response = Http::get('https://example.com');

  $result = HttpResponseInspector::detectError($response);

  expect($result['is_error'])->toBeFalse();
});

// ─── detectConfiguredError (JSON-level) ──────────────────────────────────────

// Match mode: error_path + error_value

it('detects error in match mode when value matches', function () {
  $json = ['response' => ['status' => 'Error', 'errors' => ['error' => 'Lead is a duplicate']]];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.errors.error');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Lead is a duplicate');
});

it('does not detect error in match mode when value differs', function () {
  $json = ['response' => ['status' => 'Matched']];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.errors.error');

  expect($result['is_error'])->toBeFalse();
});

it('detects NOT OK status with match mode', function () {
  $json = ['ping_response' => ['data' => ['status' => 'NOT OK', 'status_messages' => [['error' => 'Already submitted']]]]];

  $result = HttpResponseInspector::detectConfiguredError($json, 'ping_response.data.status', 'NOT OK');

  expect($result['is_error'])->toBeTrue();
});

it('does not detect error for OK status in match mode', function () {
  $json = ['ping_response' => ['data' => ['status' => 'OK']]];

  $result = HttpResponseInspector::detectConfiguredError($json, 'ping_response.data.status', 'NOT OK');

  expect($result['is_error'])->toBeFalse();
});

it('detects failure outcome with reason path', function () {
  $json = ['outcome' => 'failure', 'reason' => 'Duplicate lead', 'price' => 0];

  $result = HttpResponseInspector::detectConfiguredError($json, 'outcome', 'failure', 'reason');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Duplicate lead');
});

it('passes success outcome in match mode', function () {
  $json = ['outcome' => 'success', 'price' => 5.50];

  $result = HttpResponseInspector::detectConfiguredError($json, 'outcome', 'failure', 'reason');

  expect($result['is_error'])->toBeFalse();
});

// Exists mode: error_path only (no error_value)

it('detects error in exists mode when path has truthy value', function () {
  $json = ['error' => 'Something went wrong'];

  $result = HttpResponseInspector::detectConfiguredError($json, 'error');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Something went wrong');
});

it('does not detect error in exists mode when path is null', function () {
  $json = ['data' => ['result' => 'ok']];

  $result = HttpResponseInspector::detectConfiguredError($json, 'error');

  expect($result['is_error'])->toBeFalse();
});

it('does not detect error in exists mode when path is empty string', function () {
  $json = ['error' => ''];

  $result = HttpResponseInspector::detectConfiguredError($json, 'error');

  expect($result['is_error'])->toBeFalse();
});

it('uses error_reason_path in exists mode when provided', function () {
  $json = ['has_error' => true, 'detail' => 'Connection refused by upstream'];

  $result = HttpResponseInspector::detectConfiguredError($json, 'has_error', null, 'detail');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Connection refused by upstream');
});

// Edge cases

it('returns no error when error_path is null', function () {
  $json = ['anything' => 'here'];

  $result = HttpResponseInspector::detectConfiguredError($json, null);

  expect($result['is_error'])->toBeFalse();
});

it('returns fallback reason when reason path has no value', function () {
  $json = ['response' => ['status' => 'Error']];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.message');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toContain('Error detected at');
});

it('handles deeply nested paths', function () {
  $json = ['level1' => ['level2' => ['level3' => ['status' => 'fail', 'msg' => 'Timeout upstream']]]];

  $result = HttpResponseInspector::detectConfiguredError($json, 'level1.level2.level3.status', 'fail', 'level1.level2.level3.msg');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Timeout upstream');
});

// Pipe-separated reason paths

it('uses first matching pipe-separated reason path', function () {
  $json = ['response' => ['status' => 'Error', 'errors' => ['error' => 'Lead is a duplicate']]];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.errors.error|response.error');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Lead is a duplicate');
});

it('falls back to second pipe-separated reason path when first is empty', function () {
  $json = ['response' => ['status' => 'Error', 'error' => 'Insert Error #8: Required value missing.']];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.errors.error|response.error');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toBe('Insert Error #8: Required value missing.');
});

it('uses fallback when no pipe-separated reason path matches', function () {
  $json = ['response' => ['status' => 'Error']];

  $result = HttpResponseInspector::detectConfiguredError($json, 'response.status', 'Error', 'response.errors.error|response.error');

  expect($result['is_error'])->toBeTrue();
  expect($result['reason'])->toContain('Error detected at');
});
