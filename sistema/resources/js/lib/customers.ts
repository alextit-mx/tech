export interface SatCatalogs {
    regimes: Record<
        string,
        { name: string; persons: ('physical' | 'moral')[] }
    >;
    cfdiUses: Record<string, string>;
    states: string[];
}

export const emptyCustomer = () => ({
    kind: 'mostrador' as 'mostrador' | 'registrado',
    name: '',
    phone: '',
    email: '',
    rfc: '',
    notes: '',
    company: '',
    position: '',
    legal_name: '',
    tax_regime: '',
    cfdi_use: '',
    tax_zip: '',
    street: '',
    ext_number: '',
    int_number: '',
    neighborhood: '',
    city: '',
    state: '',
    zip: '',
    address_notes: '',
});
export type CustomerData = ReturnType<typeof emptyCustomer>;
export type PickedCustomer = {
    id: number;
    name: string;
    phone: string | null;
} & Partial<{
    [K in Exclude<keyof CustomerData, 'name' | 'phone'>]:
        | CustomerData[K]
        | null;
}>;

export function customerAddress(customer: PickedCustomer): string {
    return [
        [
            customer.street,
            customer.ext_number,
            customer.int_number ? `Int. ${customer.int_number}` : '',
        ]
            .filter(Boolean)
            .join(' '),
        customer.neighborhood,
        customer.city,
        customer.state,
        customer.zip ? `CP ${customer.zip}` : '',
    ]
        .filter(Boolean)
        .join(', ');
}
