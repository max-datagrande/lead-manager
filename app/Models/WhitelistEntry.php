<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhitelistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope para obtener solo entradas activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para obtener solo entradas inactivas
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope para filtrar por tipo específico
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para obtener solo dominios
     */
    public function scopeDomains($query)
    {
        return $query->where('type', 'domain');
    }

    /**
     * Scope para obtener solo IPs
     */
    public function scopeIps($query)
    {
        return $query->where('type', 'ip');
    }

    /**
     * Accessor para obtener el tipo formateado
     */
    public function getTypeFormattedAttribute()
    {
        return $this->type === 'domain' ? 'Dominio' : 'IP';
    }

    /**
     * Accessor para obtener el estado formateado
     */
    public function getStatusFormattedAttribute()
    {
        return $this->is_active ? 'Activo' : 'Inactivo';
    }
}