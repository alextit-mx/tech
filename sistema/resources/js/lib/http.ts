/** Peticiones JSON al backend sin recargar la página (con la cookie XSRF de Laravel y progreso de subida). */
export type HttpResult<T> = { ok: boolean; status: number; data: T };

function xsrf(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Primer mensaje de error de una respuesta de Laravel (422 o excepción). */
export function firstError(
    data: unknown,
    fallback = 'No se pudo completar la acción.',
): string {
    const body = data as {
        errors?: Record<string, string[]>;
        message?: string;
    } | null;
    const errors = body?.errors ? Object.values(body.errors).flat() : [];

    return errors[0] ?? body?.message ?? fallback;
}

export function http<T = unknown>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: FormData | Record<string, unknown>,
    onProgress?: (fraction: number) => void,
): Promise<HttpResult<T>> {
    return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrf());
        if (onProgress) {
            xhr.upload.onprogress = (e) =>
                e.lengthComputable && onProgress(e.loaded / e.total);
        }
        xhr.onload = () => {
            let data: unknown = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                data = null;
            }
            resolve({
                ok: xhr.status >= 200 && xhr.status < 300,
                status: xhr.status,
                data: data as T,
            });
        };
        xhr.onerror = () =>
            resolve({
                ok: false,
                status: 0,
                data: { message: 'Sin conexión con el servidor.' } as T,
            });
        if (body instanceof FormData || body === undefined) {
            xhr.send(body ?? null);
        } else {
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(body));
        }
    });
}
