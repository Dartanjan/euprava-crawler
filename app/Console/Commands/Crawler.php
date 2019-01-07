<?php

namespace App\Console\Commands;

use App\Crawl;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Console\Command;
use PHPHtmlParser\Dom;

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
    protected $description = 'Crawl eUprava website for the list of services';

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
     * Run the crawler
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Crawling...");

        $newServices = $this->getNewServices();

        if ($newServices->isNotEmpty()) {
            $messages = $newServices->map(function ($service) {
                return sprintf("・ %s (%s) (%s)", $service->naziv, $service->e_usluga ? "e-usluga" : "nije e-usluga", $service->url());
            });

            info("New or changed items:", $messages->toArray());

            return $this->notify($messages);
        }

        $this->info("No new or updated services found.");
    }

    /**
     * Crawl the site and return list of new services
     *
     * @return \Illuminate\Support\Collection
     */
    public function getNewServices() : Collection
    {
        $dom = (new Dom)->loadFromUrl('https://www.euprava.gov.rs/eusluge/usluge_po_slovu?alphabet=lat');
        $items = $dom->find('#main #content ul li > a');
        $newServices = collect();

        foreach($items->toArray() as $element) {
            preg_match('/generatedServiceId=([0-9]*)/', $element->getAttribute('href'), $matches);

            if (! isset($matches[1])) {
                continue;
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

                $newServices->push($item);
            }
        };

        return $newServices;
    }

    /**
     * Notify our Slack channel about new services
     *
     * @param \Illuminate\Support\Collection $messages
     */
    public function notify(Collection $messages)
    {
        $text = "🙌 Novi servis na eUpravi:\n" . $messages->implode(PHP_EOL);

        $client = new Client;
        $client->post(config('services.slack'), [
            'json' => [
                'text' => $text
            ]
        ]);
    }
}
