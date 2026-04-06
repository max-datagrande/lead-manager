<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

it('stores outgoing email html files in logs emails directory', function () {
  $directory = storage_path('logs/emails');

  File::deleteDirectory($directory);

  Mail::raw('Plain body for testing.', function ($message) {
    $message->to('recipient@example.com')->subject('Welcome Subject');
  });

  expect(File::exists($directory))->toBeTrue();

  $files = File::files($directory);

  expect($files)->not->toBeEmpty();

  $matchingFiles = collect($files)->filter(function ($file) {
    return preg_match('/^\d{8}_.+_welcome_subject(\_\d{2})?\.html$/', $file->getFilename()) === 1 && str_contains($file->getFilename(), 'recipient');
  });

  expect($matchingFiles)->not->toBeEmpty();

  $hasHtmlWrapper = $matchingFiles->contains(function ($file) {
    return str_contains(File::get($file->getPathname()), '<html><body><pre>');
  });

  expect($hasHtmlWrapper)->toBeTrue();
});
