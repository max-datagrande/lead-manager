<?php

namespace App\Libraries;

use Maxidev\Logger\TailLogger as Logger;

class Slack
{
  private $webhookUrl;
  private $defaultUsername;
  private $defaultIcon;
  private $title;

  /**
   * Slack service constructor
   *
   * @param string $webhookUrl Slack webhook URL (optional, defaults to SLACK_WEBHOOK_URL from .env)
   * @param string $defaultUsername Default username (optional)
   * @param string $defaultIcon Default icon (optional)
   * @param string $title Default title (optional)
   */
  public function __construct(?string $webhookUrl = null, string $defaultUsername = 'Bot', string $defaultIcon = ':robot_face:')
  {
    $this->webhookUrl = $webhookUrl ?? env('SLACK_WEBHOOK_URL') ?? null;
    $this->defaultUsername = $defaultUsername;
    $this->defaultIcon = $defaultIcon;
  }

  /**
   * Sends a message to Slack
   *
   * @param string $message Message to send
   * @param array $options Additional options (username, icon, attachments)
   * @return bool Success or failure of the sending
   */
  public function sendMessage(string $message, array $options = []): bool
  {
    if (!$this->webhookUrl) {
      Logger::saveLog('Slack webhook URL is not configured', 'api/slack/errors/' . date('Y-m-d') . '.log');
      return false;
    }
    if ($options['title'] ?? null) {
      $title = $options['title'];
      unset($options['title']);
      $this->title = $title;
    }
    // Check if the message appears to be HTML and format it appropriately
    if ($this->isHtmlContent($message)) {
      return $this->sendHtmlMessage($message, $options);
    }

    $username = $options['username'] ?? $this->defaultUsername;
    $icon = $options['icon'] ?? $this->defaultIcon;
    $attachments = isset($options['attachments'])
      ? $this->parseAttachments($options['attachments'])
      : [];

    $payload = [
      'text' => $message,
    ];
    if ($username) {
      $payload['username'] = $username;
    }

    if ($icon) {
      if (strpos($icon, ':') === 0) {
        $payload['icon_emoji'] = $icon;
      } else {
        $payload['icon_url'] = $icon;
      }
    }

    if (!empty($attachments)) {
      $payload['attachments'] = $attachments;
    }
    return $this->sendPayload($payload);
  }
  private function parseAttachments(array $attachments): array
  {
    if (empty($attachments)) {
      return [];
    }
    if (!is_array($attachments)) {
      return [];
    }
    // Lógica global para formatear Fingerprint
    if (!empty($attachments)) {
      foreach ($attachments as &$attachment) { // Usar referencia para modificar el array original
        if (isset($attachment['fields']) && is_array($attachment['fields'])) {
          foreach ($attachment['fields'] as &$field) { // Usar referencia
            if (isset($field['title']) && $field['title'] === 'Fingerprint' && isset($field['value'])) {
              $field['value'] = '`' . $field['value'] . '`'; // Añadir '>' para blockquote
              $field['short'] = false; // Asegurar ancho completo
            }
          }
          unset($field); // Romper la referencia del bucle interno
        }
      }
      unset($attachment); // Romper la referencia del bucle externo
    }
    return $attachments;
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

  /**
   * Sends an HTML message properly formatted
   *
   * @param string $htmlContent HTML content
   * @param array $options Additional options
   * @return bool Success or failure of the sending
   */
  private function sendHtmlMessage(string $htmlContent, array $options = []): bool
  {
    // Extract page title if it exists
    $title = "HTML Content";
    if (preg_match('/<title>(.*?)<\/title>/i', $htmlContent, $matches)) {
      $title = $matches[1];
    }

    // Extract error message if it exists
    $textContent = "Could not extract specific text";
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

    $attachments = [
      [
        'color' => '#FF9900',
        'title' => $title,
        'text' => $textContent,
        'fields' => [
          [
            'title' => 'Content Type',
            'value' => 'HTML',
          ]
        ],
        'footer' => 'HTML content detected'
      ]
    ];

    // Merge with existing attachments if any
    if (isset($options['attachments']) && is_array($options['attachments'])) {
      $attachments = array_merge($attachments, $options['attachments']);
    }

    $options['attachments'] = $attachments;
    $message = $this->title
      ? "*{$this->title}:* HTML content detected"
      : "HTML content detected";
    return $this->sendMessage($message, $options);
  }

  /**
   * Sends a payload to Slack
   *
   * @param array $payload Payload to send
   * @return bool Success or failure of the sending
   */
  private function sendPayload(array $payload): bool
  {
    $jsonPayload = json_encode($payload);

    $ch = curl_init($this->webhookUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Content-Length: ' . strlen($jsonPayload)
    ]);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
      Logger::saveLog("Error sending message to Slack: $error", 'api/slack/errors/' . date('Y-m-d') . '.log');
      return false;
    }

    if ($httpCode != 200) {
      Logger::saveLog("Error sending message to Slack. HTTP Code: $httpCode, Response: $result", 'api/slack/errors/' . date('Y-m-d') . '.log');
      return false;
    }

    return true;
  }

  /**
   * Sends a generic error notification
   *
   * @param string $title Error title
   * @param string $message Error message
   * @param array $fields Additional fields as key-value pairs
   * @param array $options Additional options for the message
   * @return bool Success or failure of the sending
   */
  public function sendErrorNotification(string $title, string $message, array $fields = [], array $options = []): bool
  {
    $attachmentFields = [];

    // Convert fields to attachment format
    foreach ($fields as $key => $value) {
      $attachmentFields[] = [
        'title' => $key,
        'value' => is_array($value) || is_object($value) ? json_encode($value) : (string)$value,
        'short' => strlen((string)$value) < 50
      ];
    }

    $attachments = [
      [
        'color' => '#FF0000',
        'title' => $title,
        'text' => $message,
        'fields' => $attachmentFields,
        'footer' => 'Error detected - ' . date('Y-m-d H:i:s'),
        'ts' => time()
      ]
    ];

    // Merge with existing attachments if any
    if (isset($options['attachments']) && is_array($options['attachments'])) {
      $attachments = array_merge($attachments, $options['attachments']);
    }

    $options['attachments'] = $attachments;
    return $this->sendMessage("An error has been detected", $options);
  }
}
