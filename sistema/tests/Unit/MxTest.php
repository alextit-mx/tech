<?php

namespace Tests\Unit;

use App\Support\Mx;
use PHPUnit\Framework\TestCase;

class MxTest extends TestCase
{
    public function test_amount_in_words(): void
    {
        $this->assertSame('CIENTO NOVENTA Y DOS MIL NOVECIENTOS NOVENTA Y SIETE PESOS 85/100 M.N.', Mx::amountInWords(192997.85));
        $this->assertSame('UN PESO 00/100 M.N.', Mx::amountInWords(1));
        $this->assertSame('CIEN PESOS 50/100 M.N.', Mx::amountInWords(100.5));
        $this->assertSame('VEINTIÚN MIL CIENTO VEINTIÚN PESOS 05/100 M.N.', Mx::amountInWords(21121.05));
        $this->assertSame('UN MILLÓN MIL PESOS 00/100 M.N.', Mx::amountInWords(1001000));
        $this->assertSame('DOS MILLONES DE PESOS 00/100 M.N.', Mx::amountInWords(2000000));
        $this->assertSame('CERO PESOS 99/100 M.N.', Mx::amountInWords(0.99));
    }

    public function test_phone(): void
    {
        $this->assertSame('55 4911 4435', Mx::phone('525549114435'));
        $this->assertSame('55 4441 1250', Mx::phone('5544411250'));
        $this->assertSame('123', Mx::phone('123'));
        $this->assertSame('', Mx::phone(null));
    }
}
