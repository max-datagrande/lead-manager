<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class SlackNotification extends Notification
{
  use Queueable;

  /**
   * Create a new notification instance.
   */
  public function __construct(
    protected string $title,
    protected string $body,
    protected string $level = 'info',
    protected array $fields = [],
    protected ?string $footer = null,
  ) {}

  /**
   * Get the notification's delivery channels.
   *
   * @return array<int, string>
   */
  public function via(object $notifiable): array
  {
    return ['slack'];
  }

  /**
   * Get the Slack representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return \Illuminate\Notifications\Messages\SlackMessage
   */
  public function toSlack($notifiable): SlackMessage
  {
    $message = new SlackMessage();
    $message->from('Datagrande-BOT', ':robot_face:')
      ->to('#top-car-errors');

    // Setear color según nivel
    match ($this->level) {
      'error' => $message->error(),
      'warning' => $message->warning(),
      default => $message->info(),
    };
    $title = $this->title;
    $body = $this->isHtmlContent($this->body) ? $this->formatHtml($this->body) : "```{$this->body}```";
    $message->content(":warning: *$title*");

    $message->attachment(function ($attachment) use ($body) {
      $attachment->title($this->title)
        ->content($body)->color('#f34235');
    });
    $message->attachment(function ($attachment) {
      foreach ($this->fields as $title => $value) {
        $attachment->field(function ($field) use ($title, $value) {
          $field->title($title)->content("`$value`")->long();
        });
      }
      // Footer opcional
      if ($this->footer) {
        $attachment->footer($this->footer);
      }
    })->info();

    return $message;
  }
  public function formatHtml(string $htmlContent): string
  {
    // Extract page title if it exists
    $title = "HTML Content";
    $textContent = "Could not extract specific text";
    if (preg_match('/<title>(.*?)<\/title>/i', $htmlContent, $matches)) {
      $title = $matches[1];
      $this->title = $title; // Guardar el título para posteriormente
    }
    // Extract error message if it exists
    if (preg_match('/<body>(.*?)<\/body>/is', $htmlContent, $matches)) {
      $bodyContent = $matches[1];
      // Try to extract meaningful text (first 500 characters)
      $plainText = strip_tags($bodyContent);

      // Mejor limpieza de espacios en blanco y saltos de línea
      // 1. Normalizar todos los saltos de línea a \n
      $plainText = str_replace(["\r\n", "\r"], "\n", $plainText);

      // 2. Reemplazar múltiples espacios horizontales con uno solo
      $plainText = preg_replace('/[ \t]+/', ' ', $plainText);

      // 3. Reemplazar 3 o más saltos de línea con 2 (para separar párrafos)
      $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText);

      // 4. Eliminar espacios al inicio y final de cada línea
      $lines = explode("\n", $plainText);
      $cleanedLines = [];
      foreach ($lines as $line) {
        $trimmed = trim($line);
        if (!empty($trimmed)) {
          $cleanedLines[] = $trimmed;
        }
      }

      // 5. Reconstruir el texto con saltos de línea
      $plainText = implode("\n", $cleanedLines);

      // 6. Eliminar espacios al inicio y final del texto completo
      $plainText = trim($plainText);

      $textContent = substr($plainText, 0, 500) . (strlen($plainText) > 500 ? '...' : '');
    }
    return $textContent;
  }
  /**
   * Checks if the content appears to be HTML
   *
   * @param string $content Content to check
   * @return bool True if it appears to be HTML, false if not
   */
  private function isHtmlContent(string $content): bool
  {
    // Check if the response starts with common HTML tags
    if (preg_match('/<(!DOCTYPE|html|head|body)/i', $content)) {
      return true;
    }

    // Check if it contains common HTML tags
    if (preg_match('/<(div|span|p|h1|h2|h3|table|form|a)\s/i', $content)) {
      return true;
    }

    return false;
  }
  public function toArray(object $notifiable): array
  {
    return [
      'title' => $this->title,
      'body' => $this->body,
      'level' => $this->level,
      'fields' => $this->fields,
      'footer' => $this->footer,
    ];
  }
}
