<?php

namespace App\Console\Commands;

use App\Crawl;
use Carbon\Carbon;
use PHPHtmlParser\Dom;
use Illuminate\Console\Command;

class Crawler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Krouluje sajt euprave';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Crawling...");

        $dom = (new Dom)->loadFromUrl('https://www.euprava.gov.rs/eusluge/usluge_po_slovu?alphabet=lat');
        $items = $dom->find('#main #content ul li > a');
        $newItems = collect();

        array_map(function ($element) use ($newItems) {
            preg_match('/generatedServiceId=([0-9]*)/', $element->getAttribute('href'), $matches);

            if (! isset($matches[1])) {
                return [];
            }

            $item = Crawl::firstOrNew([
                'id_usluge' => $matches[1],
                'naziv' => trim(str_replace('"', "'", $element->lastChild()->text)),
                'e_usluga' => strpos($element->innerHtml(), '(e-usluga)') !== false,
                'dokument' => strpos($element->innerHtml(), '(ima dokument)') !== false,
                'privreda' => strpos($element->innerHtml(), '(pravna lica)') !== false,
                'zakazivanje' => strpos($element->innerHtml(), '(e-zakazivanje)') !== false,
            ]);

            if (! $item->exists) {
                $item->vreme = Carbon::now()->toDateTimeString();
                $item->save();

                $newItems->push($item);
            }
        }, $items->toArray());

        $this->info("Done.");

        if ($newItems->isNotEmpty()) {
            $this->info("Found new or changed services:");
            $newItems->each(function ($service) {
                $this->info($service->naziv);
            });
        }
    }
}
