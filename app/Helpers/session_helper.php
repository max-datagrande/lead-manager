<?php

if (!function_exists('add_flash_message')) {
  /**
   * Agrega un mensaje flash a la sesión.
   *
   * @param  string  $type  El tipo de mensaje (e.g., 'success', 'error', 'warning', 'info').
   * @param  string  $message  El mensaje a mostrar.
   */
  function add_flash_message(string $type, string $message): void
  {
    $messages = session()->get($type, []);
    $messages[] = $message;
    session()->flash($type, $messages);
  }
}
