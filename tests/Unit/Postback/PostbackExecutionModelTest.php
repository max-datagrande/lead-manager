<?php

use App\Enums\ExecutionStatus;
use App\Models\PostbackExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('generateIdempotencyKey', function () {
    it('is deterministic — same inputs produce the same key', function () {
        $key1 = PostbackExecution::generateIdempotencyKey(42, ['click_id' => 'ABC', 'payout' => '9.99']);
        $key2 = PostbackExecution::generateIdempotencyKey(42, ['click_id' => 'ABC', 'payout' => '9.99']);

        expect($key1)->toBe($key2);
    });

    it('is order-independent — params sorted before hashing', function () {
        $key1 = PostbackExecution::generateIdempotencyKey(42, ['click_id' => 'ABC', 'payout' => '9.99']);
        $key2 = PostbackExecution::generateIdempotencyKey(42, ['payout' => '9.99', 'click_id' => 'ABC']);

        expect($key1)->toBe($key2);
    });

    it('produces a different key when params differ', function () {
        $key1 = PostbackExecution::generateIdempotencyKey(42, ['click_id' => 'ABC']);
        $key2 = PostbackExecution::generateIdempotencyKey(42, ['click_id' => 'XYZ']);

        expect($key1)->not->toBe($key2);
    });
});

describe('status machine helpers', function () {
    it('markAsDispatching sets status and dispatched_at', function () {
        $execution = PostbackExecution::factory()->pending()->create();

        $execution->markAsDispatching();
        $execution->refresh();

        expect($execution->status)->toBe(ExecutionStatus::DISPATCHING);
        expect($execution->dispatched_at)->not->toBeNull();
    });

    it('markAsCompleted sets status and completed_at', function () {
        $execution = PostbackExecution::factory()->dispatching()->create();

        $execution->markAsCompleted();
        $execution->refresh();

        expect($execution->status)->toBe(ExecutionStatus::COMPLETED);
        expect($execution->completed_at)->not->toBeNull();
    });

    it('markAsFailed on attempt 1 schedules retry in 60 seconds', function () {
        Date::setTestNow('2026-01-01 12:00:00');

        $execution = PostbackExecution::factory()->create(['attempts' => 1, 'max_attempts' => 5]);

        $execution->markAsFailed();
        $execution->refresh();

        expect($execution->status)->toBe(ExecutionStatus::FAILED);
        expect($execution->next_retry_at->timestamp)->toBe(
            now()->addSeconds(60)->timestamp
        );

        Date::setTestNow();
    });

    it('markAsFailed on attempt 2 applies exponential backoff (120s)', function () {
        Date::setTestNow('2026-01-01 12:00:00');

        $execution = PostbackExecution::factory()->create(['attempts' => 2, 'max_attempts' => 5]);

        $execution->markAsFailed();
        $execution->refresh();

        expect($execution->next_retry_at->timestamp)->toBe(
            now()->addSeconds(120)->timestamp
        );

        Date::setTestNow();
    });

    it('markAsFailed when attempts >= max_attempts sets next_retry_at to null', function () {
        $execution = PostbackExecution::factory()->failedExhausted()->create();

        // Already at max; calling markAsFailed again should leave next_retry_at null
        $execution->markAsFailed();
        $execution->refresh();

        expect($execution->next_retry_at)->toBeNull();
    });
});

describe('scopeRetryable', function () {
    it('includes failed executions with attempts < max and past next_retry_at', function () {
        PostbackExecution::factory()->failedRetryable()->create();

        expect(PostbackExecution::query()->retryable()->count())->toBe(1);
    });

    it('excludes failed executions where next_retry_at is in the future', function () {
        PostbackExecution::factory()->failed()->create(); // next_retry_at = now+60s

        expect(PostbackExecution::query()->retryable()->count())->toBe(0);
    });

    it('excludes completed executions', function () {
        PostbackExecution::factory()->completed()->create();

        expect(PostbackExecution::query()->retryable()->count())->toBe(0);
    });

    it('excludes exhausted failed executions (attempts = max)', function () {
        PostbackExecution::factory()->failedExhausted()->create();

        expect(PostbackExecution::query()->retryable()->count())->toBe(0);
    });
});
