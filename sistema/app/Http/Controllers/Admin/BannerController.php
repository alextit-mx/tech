<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\WebpImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Banners del carrusel de la tienda. Los administran dueño y vendedores. */
class BannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Banners', ['banners' => Banner::orderBy('sort')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['image'] = WebpImage::store($request->file('image'), 'banners', 2400);
        if ($request->hasFile('image_mobile')) {
            $data['image_mobile'] = WebpImage::store($request->file('image_mobile'), 'banners', 1200);
        }
        $data['sort'] = (int) Banner::max('sort') + 1;
        Banner::create($data);

        return back()->with('success', 'Banner agregado.');
    }

    /** Se envía por POST (multipart) para poder reemplazar imágenes. */
    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validated($request, false);
        foreach (['image' => 2400, 'image_mobile' => 1200] as $field => $size) {
            if ($request->hasFile($field)) {
                Storage::disk('uploads')->delete((string) $banner->{$field});
                $data[$field] = WebpImage::store($request->file($field), 'banners', $size);
            }
        }
        $banner->update($data);

        return back()->with('success', 'Banner guardado.');
    }

    public function move(Request $request, Banner $banner): RedirectResponse
    {
        $direction = $request->integer('direction') < 0 ? -1 : 1;
        $banners = Banner::orderBy('sort')->orderBy('id')->get()->values();
        $from = $banners->search(fn (Banner $b) => $b->id === $banner->id);
        $to = max(0, min($banners->count() - 1, $from + $direction));
        $moved = $banners->splice($from, 1)->first();
        $banners->splice($to, 0, [$moved]);
        $banners->each(fn (Banner $b, int $i) => $b->update(['sort' => $i]));

        return back();
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        Storage::disk('uploads')->delete(array_filter([$banner->image, $banner->image_mobile]));
        $banner->delete();

        return back()->with('success', 'Banner eliminado.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'image_mobile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'alt' => ['required', 'string', 'max:160'],
            'link' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [], ['image' => 'imagen', 'image_mobile' => 'imagen para celular', 'alt' => 'descripción', 'ends_at' => 'fin']);
    }
}
