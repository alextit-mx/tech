<?php

namespace App\Support;

class Sat
{
    // Catálogos CFDI 4.0: https://www.sat.gob.mx/sitio_internet/cfd/catalogos/catCFDI.xls
    public const REGIMES = [
        '601' => ['name' => 'General de Ley Personas Morales', 'persons' => ['moral']],
        '603' => ['name' => 'Personas Morales con Fines no Lucrativos', 'persons' => ['moral']],
        '605' => ['name' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios', 'persons' => ['physical']],
        '606' => ['name' => 'Arrendamiento', 'persons' => ['physical']],
        '607' => ['name' => 'Régimen de Enajenación o Adquisición de Bienes', 'persons' => ['physical']],
        '608' => ['name' => 'Demás ingresos', 'persons' => ['physical']],
        '610' => ['name' => 'Residentes en el Extranjero sin Establecimiento Permanente en México', 'persons' => ['physical', 'moral']],
        '611' => ['name' => 'Ingresos por Dividendos (socios y accionistas)', 'persons' => ['physical']],
        '612' => ['name' => 'Personas Físicas con Actividades Empresariales y Profesionales', 'persons' => ['physical']],
        '614' => ['name' => 'Ingresos por intereses', 'persons' => ['physical']],
        '615' => ['name' => 'Régimen de los ingresos por obtención de premios', 'persons' => ['physical']],
        '616' => ['name' => 'Sin obligaciones fiscales', 'persons' => ['physical']],
        '620' => ['name' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos', 'persons' => ['moral']],
        '621' => ['name' => 'Incorporación Fiscal', 'persons' => ['physical']],
        '622' => ['name' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'persons' => ['moral']],
        '623' => ['name' => 'Opcional para Grupos de Sociedades', 'persons' => ['moral']],
        '624' => ['name' => 'Coordinados', 'persons' => ['moral']],
        '625' => ['name' => 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'persons' => ['physical']],
        '626' => ['name' => 'Régimen Simplificado de Confianza', 'persons' => ['physical', 'moral']],
    ];

    public const CFDI_USES = [
        'G01' => 'Adquisición de mercancías', 'G02' => 'Devoluciones, descuentos o bonificaciones', 'G03' => 'Gastos en general',
        'I01' => 'Construcciones', 'I02' => 'Mobiliario y equipo de oficina por inversiones', 'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo y accesorios', 'I05' => 'Dados, troqueles, moldes, matrices y herramental',
        'I06' => 'Comunicaciones telefónicas', 'I07' => 'Comunicaciones satelitales', 'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios', 'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales', 'D04' => 'Donativos', 'D05' => 'Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación)',
        'D06' => 'Aportaciones voluntarias al SAR', 'D07' => 'Primas por seguros de gastos médicos',
        'D08' => 'Gastos de transportación escolar obligatoria', 'D09' => 'Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones',
        'D10' => 'Pagos por servicios educativos (colegiaturas)', 'S01' => 'Sin efectos fiscales', 'CP01' => 'Pagos', 'CN01' => 'Nómina',
    ];

    public const STATES = [
        'Aguascalientes', 'Baja California', 'Baja California Sur', 'Campeche', 'Chiapas', 'Chihuahua', 'Ciudad de México',
        'Coahuila', 'Colima', 'Durango', 'Estado de México', 'Guanajuato', 'Guerrero', 'Hidalgo', 'Jalisco', 'Michoacán',
        'Morelos', 'Nayarit', 'Nuevo León', 'Oaxaca', 'Puebla', 'Querétaro', 'Quintana Roo', 'San Luis Potosí', 'Sinaloa',
        'Sonora', 'Tabasco', 'Tamaulipas', 'Tlaxcala', 'Veracruz', 'Yucatán', 'Zacatecas',
    ];

    public static function personType(string $rfc): ?string
    {
        return match (mb_strlen($rfc)) {
            12 => 'moral', 13 => 'physical', default => null,
        };
    }

    public static function validRfc(string $rfc): bool
    {
        if (! preg_match('/^[A-ZÑ&]{3,4}(\d{2})(\d{2})(\d{2})[A-Z\d]{3}$/uD', $rfc, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], 2000 + (int) $parts[1]);
    }

    public static function catalogs(): array
    {
        return ['regimes' => self::REGIMES, 'cfdiUses' => self::CFDI_USES, 'states' => self::STATES];
    }
}
