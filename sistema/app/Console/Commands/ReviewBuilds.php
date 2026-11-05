<?php

namespace App\Console\Commands;

use App\Support\BuildReviewer;
use Illuminate\Console\Command;

class ReviewBuilds extends Command
{
    protected $signature = 'equipos:revisar';

    protected $description = 'Revisa equipos, despublica los que tienen problemas e indica cuáles están listos para publicar';

    public function handle(): int
    {
        $result = app(BuildReviewer::class)->review();
        foreach ($result['results'] as $build) {
            $message = $build['name'].' ('.$build['status'].')';
            if ($build['problems']) {
                $this->warn($message.': '.implode(' ', $build['problems']));
            } else {
                $this->info($message);
            }
        }
        $this->info("Publicados: {$result['published']}; despublicados: {$result['unpublished']}.");
        $this->info("Listos para publicar: {$result['ready']}");

        return self::SUCCESS;
    }
}
