<?php

namespace App\Support;

use App\Models\Build;

class BuildReviewer
{
    public function review(): array
    {
        $results = [];
        $published = $unpublished = $ready = 0;
        foreach (Build::with('products.category')->orderBy('id')->get() as $build) {
            $problems = $build->publicationProblems();
            if ($build->is_published && $problems) {
                $build->update(['is_published' => false]);
                $status = 'despublicado';
                $unpublished++;
            } elseif ($build->is_published) {
                $status = 'publicado';
                $published++;
            } elseif ($problems === []) {
                $status = 'listo';
                $ready++;
            } else {
                $status = 'con_problemas';
            }
            $results[] = ['id' => $build->id, 'name' => $build->name, 'status' => $status, 'problems' => $problems];
        }

        return compact('published', 'unpublished', 'ready', 'results');
    }
}
