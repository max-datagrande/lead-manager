<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Enums\LeadQuality\ValidationType;

it('LeadQualityProviderType implements the enum contract', function () {
  expect(LeadQualityProviderType::fromValue('twilio_verify'))->toBe(LeadQualityProviderType::TWILIO_VERIFY);
  expect(LeadQualityProviderType::fromValue('nope'))->toBeNull();
  expect(LeadQualityProviderType::isValid('ipqs'))->toBeTrue();
  expect(LeadQualityProviderType::isValid('nope'))->toBeFalse();
  expect(LeadQualityProviderType::TWILIO_VERIFY->label())->toBe('Twilio Verify');
});

it('ValidationType exposes is_async flag', function () {
  expect(ValidationType::OTP_PHONE->isAsync())->toBeTrue();
  expect(ValidationType::OTP_EMAIL->isAsync())->toBeTrue();
  expect(ValidationType::PHONE_LOOKUP->isAsync())->toBeFalse();
  expect(ValidationType::IPQS_SCORE->isAsync())->toBeFalse();
});

it('ValidationLogStatus isTerminal covers terminal states', function () {
  expect(ValidationLogStatus::VERIFIED->isTerminal())->toBeTrue();
  expect(ValidationLogStatus::FAILED->isTerminal())->toBeTrue();
  expect(ValidationLogStatus::EXPIRED->isTerminal())->toBeTrue();
  expect(ValidationLogStatus::PENDING->isTerminal())->toBeFalse();
  expect(ValidationLogStatus::SENT->isTerminal())->toBeFalse();
});

it('RuleStatus and ProviderStatus expose toArray', function () {
  expect(RuleStatus::toArray())->toHaveCount(3);
  expect(ProviderStatus::toArray())->toHaveCount(3);
});

it('DispatchStatus includes PENDING_VALIDATION as non-terminal initial state', function () {
  expect(DispatchStatus::tryFrom('pending_validation'))->toBe(DispatchStatus::PENDING_VALIDATION);
  expect(DispatchStatus::PENDING_VALIDATION->isTerminal())->toBeFalse();
  expect(DispatchStatus::PENDING_VALIDATION->label())->toBe('Pending Validation');
});

it('DispatchStatus includes VALIDATION_FAILED as terminal state', function () {
  expect(DispatchStatus::tryFrom('validation_failed'))->toBe(DispatchStatus::VALIDATION_FAILED);
  expect(DispatchStatus::VALIDATION_FAILED->isTerminal())->toBeTrue();
  expect(DispatchStatus::VALIDATION_FAILED->label())->toBe('Validation Failed');
});
