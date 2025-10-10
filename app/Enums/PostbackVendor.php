<?php

namespace App\Enums;

enum PostbackVendor: string
{
  case NI = 'ni';
  case MAXCONV = 'maxconv';

  /**
   * Obtiene el nombre legible del vendor
   */
  public function label(): string
  {
    return match ($this) {
      self::NI => 'Natural Intelligence',
      self::MAXCONV => 'MaxConv',
    };
  }

  /**
   * Obtiene el valor del vendor
   */
  public function value(): string
  {
    return $this->value;
  }

  /**
   * Convierte todos los vendors a un array para uso en frontend
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $vendor) => [
        'value' => $vendor->value(),
        'label' => $vendor->label(),
      ],
      self::cases()
    );
  }

  /**
   * Obtiene un vendor por su valor
   */
  public static function fromValue(string $value): ?self
  {
    return self::tryFrom($value);
  }

  /**
   * Verifica si un valor es un vendor válido
   */
  public static function isValid(string $value): bool
  {
    return self::tryFrom($value) !== null;
  }
}
