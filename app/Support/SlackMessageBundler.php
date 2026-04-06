<?php

namespace App\Support;

use Maxidev\Logger\TailLogger as Logger;
use Spatie\SlackAlerts\Facades\SlackAlert;

/**
 * SlackMessageBundler
 *
 * Clase para acumular bloques de mensajes de Slack y enviarlos al final.
 * Permite construir mensajes ricos usando el Block Kit de Slack con métodos descriptivos.
 *
 * Ejemplo de uso para reportar un error (usando jobs):
 *
 * ```php
 * use App\Support\SlackMessageBundler;
 *
 * $slack = new SlackMessageBundler();
 *
 * $slack->addTitle('Fallo en herramienta', '🚨')
 *       ->addSection("Se detectó un error crítico al ejecutar el proceso de *importación*.")
 *       ->addDivider()
 *       ->addButton('Ver logs', 'https://miapp.com/logs/error/123', 'danger')
 *       ->addFooter('Reportado automáticamente por el sistema', 'https://img.icons8.com/color/48/000000/error.png')
 *       ->send('errors');
 * ```
 *
 * Ejemplo de uso para envío directo por webhook (sin jobs):
 *
 * ```php
 * $slack = new SlackMessageBundler();
 *
 * $slack->addTitle('Notificación Urgente')
 *       ->addSection('Este mensaje se envía inmediatamente por webhook.')
 *       ->sendDirect('errors'); // Usa el canal configurado en slack-alerts.webhook_urls.errors
 * ```
 */

class SlackMessageBundler
{
  /** @var array Bloques acumulados */
  protected array $blocks = [];
  /** @var array Attachments acumulados */
  protected array $attachments = [];
  /** @var int|null Índice del attachment actualmente abierto */
  protected ?int $openAttachmentIndex = null;

  /**
   * Añadir un bloque genérico al bundle
   */
  public function addBlock(array $block): self
  {
    if ($this->openAttachmentIndex !== null && isset($this->attachments[$this->openAttachmentIndex])) {
      $this->attachments[$this->openAttachmentIndex]['blocks'][] = $block;
      return $this;
    }
    $this->blocks[] = $block;
    return $this;
  }

  /**
   * Crear un attachment con color y abrirlo para agregar bloques dentro
   */
  public function createAttachment(?string $color = null): self
  {
    $newAttachment = [
      'blocks' => [],
    ];
    if ($color) {
      $newAttachment['color'] = $color;
    }
    $this->attachments[] = $newAttachment;
    $this->openAttachmentIndex = count($this->attachments) - 1;
    return $this;
  }

  /**
   * Cerrar el attachment actualmente abierto
   */
  public function closeAttachment(): self
  {
    $this->openAttachmentIndex = null;
    return $this;
  }

  /**
   * Añadir un título grande
   */
  public function addTitle(string $text, string $emoji = ''): self
  {
    return $this->addBlock([
      'type' => 'header',
      'text' => [
        'type' => 'plain_text',
        'text' => ($emoji ? $emoji . ' ' : '') . $text,
        'emoji' => true,
      ],
    ]);
  }

  /**
   * Añadir un bloque de texto (con soporte para markdown)
   */
  public function addSection(string $text, bool $markdown = true): self
  {
    return $this->addBlock([
      'type' => 'section',
      'text' => [
        'type' => $markdown ? 'mrkdwn' : 'plain_text',
        'text' => $text,
      ],
    ]);
  }

  /**
   * Añadir un campo con ícono al inicio
   */
  public function addField(string $title, string $value, string $icon = ''): self
  {
    //Spaces for icons
    if ($icon) {
      $icon = $icon . ' ';
    }
    return $this->addBlock([
      'type' => 'section',
      'fields' => [
        [
          'type' => 'mrkdwn',
          'text' => "$icon *{$title}*\n{$value}",
        ],
      ],
    ]);
  }

  /**
   * Añadir un par clave/valor en ancho completo
   * Útil para valores largos (URLs, rutas, mensajes) evitando recortes en 'fields'.
   */
  public function addKeyValue(string $title, string $value, bool $code = false, string $icon = ''): self
  {
    if ($icon) {
      $icon = $icon . ' ';
    }
    $textContent = "$icon *{$title}*\n" . ($code ? "`{$value}`" : $value);
    return $this->addBlock([
      'type' => 'section',
      'text' => [
        'type' => 'mrkdwn',
        'text' => $textContent,
      ],
    ]);
  }

  /**
   * Añadir un divider (línea divisoria)
   */
  public function addDivider(): self
  {
    return $this->addBlock([
      'type' => 'divider',
    ]);
  }

  /**
   * Añadir un bloque con botón
   */
  public function addButton(string $text, string $url, string $style = 'primary'): self
  {
    return $this->addBlock([
      'type' => 'actions',
      'elements' => [
        [
          'type' => 'button',
          'text' => [
            'type' => 'plain_text',
            'text' => $text,
          ],
          'url' => $url,
          'style' => $style,
        ],
      ],
    ]);
  }

  /**
   * Añadir un contexto o footer (texto pequeño con ícono opcional)
   */
  public function addFooter(string $text, string $icon = ''): self
  {
    $elements = [];
    if ($icon) {
      $elements[] = [
        'type' => 'image',
        'image_url' => $icon,
        'alt_text' => 'icon',
      ];
    }
    $elements[] = [
      'type' => 'mrkdwn',
      'text' => $text,
    ];
    return $this->addBlock([
      'type' => 'context',
      'elements' => $elements,
    ]);
  }

  /**
   * Enviar los bloques acumulados a Slack usando el sistema de jobs
   */
  public function send(string $channel = 'default'): void
  {
    if (empty($this->blocks)) {
      return;
    }

    SlackAlert::to($channel)->blocks($this->blocks);

    // Limpiar después del envío para evitar duplicados
    $this->blocks = [];
  }

  /**
   * Enviar los bloques acumulados directamente por webhook (sin jobs)
   * Útil cuando necesitas envío inmediato o el sistema de colas no está disponible
   */
  public function sendDirect(string $channel = 'default'): bool
  {
    // Permitimos enviar solo attachments
    if (empty($this->blocks) && empty($this->attachments)) {
      return false;
    }

    // Buscar la URL del webhook en la configuración usando el canal
    $enabledChannels = config('slack-alerts.webhook_urls');
    $url = $enabledChannels[$channel] ?? null;
    if (empty($url)) {
      Logger::saveLog(
        "No se encontró URL de webhook para el canal '{$channel}'. Configura slack-alerts.webhook_urls.{$channel}",
        'notifications',
        'error',
      );
      return false;
    }

    // Preparar payload para Slack (siempre como attachments)
    // Si existen blocks sin attachments, los envolvemos en un attachment final sin color (gris por defecto)
    $attachmentsPayload = $this->attachments;
    $hasBlocks = count($this->blocks) > 0;
    if ($hasBlocks) {
      //Add a last attachment with no color
      $attachmentsPayload[] = [
        'blocks' => $this->blocks,
      ];
    }
    $payload = [
      'attachments' => $attachmentsPayload,
    ];

    // Enviar usando cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Limpiar después del envío
    $this->blocks = [];
    $this->attachments = [];
    $this->openAttachmentIndex = null;

    // Verificar si el envío fue exitoso
    if ($error) {
      Logger::saveLog("Error de cURL: {$error}", 'notifications', 'error', [
        'payload' => $payload,
        'response' => $response,
        'http_code' => $httpCode,
      ]);
    }

    if ($httpCode !== 200) {
      Logger::saveLog("Error HTTP {$httpCode}: {$response}", 'notifications', 'error', [
        'payload' => $payload,
        'response' => $response,
        'http_code' => $httpCode,
      ]);
      return false;
    }
    return $response === 'ok';
  }

  /**
   * Enviar el bundle acumulado a los logs (modo debug)
   * En lugar de disparar un webhook de Slack, se registra el payload
   * construido como attachments para facilitar diagnóstico.
   */
  public function sendDebugLog(string $channel = 'default'): void
  {
    // Preparar payload similar al de Slack (siempre como attachments)
    $attachmentsPayload = $this->attachments;
    $hasBlocks = count($this->blocks) > 0;
    if ($hasBlocks) {
      $attachmentsPayload[] = [
        'blocks' => $this->blocks,
      ];
    }

    $payload = [
      'attachments' => $attachmentsPayload,
    ];

    // Registrar en logs
    Logger::saveLog('Slack bundler debug output', 'notifications', 'info', [
      'channel' => $channel,
      'attachments_count' => count($attachmentsPayload),
      'payload' => $payload,
    ]);

    // Limpiar estado interno
    $this->blocks = [];
    $this->attachments = [];
    $this->openAttachmentIndex = null;
  }
}
