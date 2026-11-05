import { describe, expect, it } from 'vitest';
import fixtures from '../../../tests/fixtures/compatibility.json';
import { issues, incompatibility, warnings } from './rules';
import type { CompatibilityParts, Part, Build } from './rules';

describe('Compatibilidad compartida con PHP', () => {
    for (const fixture of fixtures) {
        it(fixture.name, () => {
            const parts = fixture.parts as CompatibilityParts;
            expect(
                issues(parts).map(({ code, level }) => ({ code, level })),
            ).toEqual(fixture.expected);
            expect(issues(parts)).toEqual(fixture.issues);
            expect(
                warnings(parts as Build).every(
                    (issue) => issue.level !== 'incompatible',
                ),
            ).toBe(true);
        });
    }
    it('incompatibility solo informa problemas de la pieza elegida', () => {
        const build = fixtures[0].parts as CompatibilityParts;
        const part = {
            id: 1,
            name: 'RAM',
            brand: null,
            image: null,
            price: 100,
            stock: 'ok',
            stockLabel: 'Entrega inmediata',
            url: '/producto/ram',
            slot: 'ram',
            specs: { ...build.ram!.specs, modules: 8 },
        } as Part;
        expect(incompatibility(part, build as Build)).toContain('8 módulos');
    });
});
