<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    public const STATUSES = [
        'received' => 'Recibido',
        'diagnosing' => 'En diagnóstico',
        'awaiting_approval' => 'Esperando autorización',
        'repairing' => 'En reparación',
        'ready' => 'Listo para entregar',
        'delivered' => 'Entregado',
        'unrepaired' => 'Sin reparación',
    ];

    /** Estados en los que el equipo sigue en el local. */
    public const OPEN = ['received', 'diagnosing', 'awaiting_approval', 'repairing', 'ready'];

    public const DEVICES = ['desktop' => 'PC de escritorio', 'laptop' => 'Laptop', 'printer' => 'Impresora', 'console' => 'Consola', 'other' => 'Otro'];

    protected $fillable = ['folio', 'customer_id', 'user_id', 'device_type', 'brand', 'model', 'serial', 'accessories', 'reported_issue', 'diagnosis', 'work_done', 'estimate', 'final_cost', 'status', 'received_at', 'promised_at', 'delivered_at'];

    protected function casts(): array
    {
        return ['estimate' => 'decimal:2', 'final_cost' => 'decimal:2', 'received_at' => 'datetime', 'promised_at' => 'date', 'delivered_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceOrderEvent::class)->latest('id');
    }

    public function device(): string
    {
        return trim((self::DEVICES[$this->device_type] ?? 'Equipo').' '.$this->brand.' '.$this->model);
    }

    /** Cambia el estado (si llega) y deja la nota en el historial. */
    public function log(?string $status, ?string $note, bool $public, ?User $user): ServiceOrderEvent
    {
        if ($status && $status !== $this->status) {
            $this->status = $status;
            $this->delivered_at = $status === 'delivered' ? now() : $this->delivered_at;
            $this->save();
        }

        return $this->events()->create(['status' => $status, 'note' => $note, 'is_public' => $public, 'user_id' => $user?->id]);
    }

    public function trackingUrl(): string
    {
        return url('/estado').'?folio='.$this->folio;
    }

    public function whatsappUrl(): ?string
    {
        if (! $this->customer?->phone) {
            return null;
        }
        $state = self::STATUSES[$this->status] ?? $this->status;
        $text = "Hola {$this->customer->name}, tu equipo con folio {$this->folio} está: {$state}. Consulta el detalle en {$this->trackingUrl()} con los últimos 4 dígitos de tu teléfono.";

        return 'https://wa.me/52'.$this->customer->phone.'?text='.rawurlencode($text);
    }
}
