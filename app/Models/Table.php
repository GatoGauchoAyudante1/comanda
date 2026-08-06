<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function sessions(): HasMany { return $this->hasMany(TableSession::class); }

    /**
     * La sesión en curso: la mesa sigue ocupada mientras su cuenta no esté
     * cobrada, aunque el reloj ya se haya frenado para cobrar. Si mirásemos
     * sólo `ended_at`, la mesa aparecería libre mientras el cliente paga y
     * otro mozo podría abrirla encima.
     */
    public function sesionAbierta(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->latest('id')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['paid', 'cancelled']));
    }

    public function esPool(): bool  { return $this->type === 'pool'; }
    public function ocupada(): bool { return $this->sesionAbierta()->exists(); }
}
