<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      return;
    }

    $channels = DB::table('alert_channels')->get(['id', 'webhook_url']);

    foreach ($channels as $channel) {
      if (empty($channel->webhook_url)) {
        continue;
      }

      try {
        Crypt::decryptString($channel->webhook_url);
      } catch (\Illuminate\Contracts\Encryption\DecryptException) {
        DB::table('alert_channels')
          ->where('id', $channel->id)
          ->update(['webhook_url' => Crypt::encryptString($channel->webhook_url)]);
      }
    }
  }

  public function down(): void
  {
    if (DB::connection()->getDriverName() === 'sqlite') {
      return;
    }

    $channels = DB::table('alert_channels')->get(['id', 'webhook_url']);

    foreach ($channels as $channel) {
      if (empty($channel->webhook_url)) {
        continue;
      }

      try {
        $decrypted = Crypt::decryptString($channel->webhook_url);
        DB::table('alert_channels')
          ->where('id', $channel->id)
          ->update(['webhook_url' => $decrypted]);
      } catch (\Illuminate\Contracts\Encryption\DecryptException) {
        // Already plain text
      }
    }
  }
};
