<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory;

    public const STATUSES = ['draft' => 'Borrador', 'sent' => 'Enviada', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada'];

    public const SOURCES = ['admin' => 'Mostrador', 'web' => 'Tienda en línea', 'builder' => 'Configurador'];

    protected $fillable = ['folio', 'customer_id', 'user_id', 'source', 'status', 'valid_until', 'build_name', 'discount', 'total', 'notes', 'internal_notes', 'public_token'];

    protected $appends = ['display_status', 'subtotal', 'tax', 'has_pending_prices'];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'discount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort');
    }

    /** "Vencida" no se guarda: es una enviada cuya vigencia ya pasó. */
    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn () => $this->status === 'sent' && $this->valid_until?->endOfDay()->isPast() ? 'expired' : $this->status);
    }

    protected function subtotal(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->total / 1.16, 2));
    }

    protected function tax(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->total - (float) $this->total / 1.16, 2));
    }

    protected function hasPendingPrices(): Attribute
    {
        // En listados sin partidas cargadas no se consulta (evita N+1).
        return Attribute::get(fn () => $this->relationLoaded('items') ? $this->items->contains(fn (QuoteItem $item) => $item->unit_price === null) : null);
    }

    /** Margen estimado sobre el precio sin IVA; solo para el dueño. */
    public function margin(): ?float
    {
        $net = (float) $this->total / 1.16;
        $cost = $this->items->sum(fn (QuoteItem $item) => $item->unit_price === null ? 0 : (float) $item->unit_cost * $item->quantity);

        return $net > 0 && $cost > 0 ? ($net - $cost) / $net : null;
    }

    public function publicUrl(): string
    {
        return route('quotes.public', $this->public_token);
    }

    public function whatsappUrl(): ?string
    {
        if (! $this->customer?->phone) {
            return null;
        }
        $text = "Hola {$this->customer->name}, te comparto tu cotización {$this->folio} de Tech Computer: {$this->publicUrl()}";

        return 'https://wa.me/52'.$this->customer->phone.'?text='.rawurlencode($text);
    }

    /** Mensaje para que el cliente avise al negocio de su cotización hecha en la web. */
    public function businessWhatsappUrl(): string
    {
        $text = "Hola Tech Computer, acabo de generar la cotización {$this->folio}".($this->build_name ? " ({$this->build_name})" : '').": {$this->publicUrl()}";

        return 'https://wa.me/'.setting('whatsapp', '525549114435').'?text='.rawurlencode($text);
    }
}
