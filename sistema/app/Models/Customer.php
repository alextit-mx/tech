<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'rfc', 'notes'];

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->latest();
    }

    /** Deja solo los 10 dígitos del teléfono (quita +52, espacios y guiones). */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : substr($digits, -10);
    }

    /** Busca por teléfono o crea el cliente; actualiza nombre y correo si llegan. */
    public static function fromContact(string $name, string $phone, ?string $email = null): self
    {
        $customer = self::firstOrNew(['phone' => self::normalizePhone($phone)]);
        $customer->name = $name;
        $customer->email = $email ?: $customer->email;
        $customer->save();

        return $customer;
    }
}
