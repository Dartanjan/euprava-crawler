<?php

namespace App\Console\Commands;

use App\Crawl;
use App\Institution;
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
     * @throws \Exception
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

        info("No new or updated services found.");

        $this->info("No new or updated services found.");
    }

    /**
     * Crawl the site and return list of new services
     *
     * @throws \Exception
     * @return \Illuminate\Support\Collection
     */
    public function getNewServices() : Collection
    {
        $dom = (new Dom)->loadFromUrl('https://www.euprava.gov.rs/eusluge/usluge_po_slovu?alphabet=lat');
        $items = $dom->find('#main #content ul li > a');
        $newServices = collect();

        foreach($items->toArray() as $element) {
            preg_match('/generatedServiceId=([0-9]*)/', $element->getAttribute('href'), $serviceMatches);

            if (! isset($serviceMatches[1])) {
                continue;
            }

            $this->info("Looking into service id " . $serviceMatches[1]);
            $serviceId = $serviceMatches[1];

            $item = Crawl::firstOrNew([
                'id_usluge' => $serviceId,
                'naziv' => trim(str_replace('"', "'", $element->lastChild()->text)),
                'e_usluga' => strpos($element->innerHtml(), '(e-usluga)') !== false,
                'dokument' => strpos($element->innerHtml(), '(ima dokument)') !== false,
                'privreda' => strpos($element->innerHtml(), '(pravna lica)') !== false,
                'zakazivanje' => strpos($element->innerHtml(), '(e-zakazivanje)') !== false,
            ]);

            if (! $item->exists) {
                $institution = $this->getInstitution($serviceId);

                $item->vreme = Carbon::now()->toDateTimeString();
                $item->id_institucije = $institution->id_institucije;
                $item->save();

                $newServices->push($item);
            }
        };

        return $newServices;
    }

    /**
     * Get Institution from a service ID
     *
     * @param int $id
     *
     * @return \App\Institution
     * @throws \Exception
     */
    public function getInstitution(int $id) : Institution
    {
        $dom = (new Dom)->loadFromUrl("https://www.euprava.gov.rs/eusluge/opis_usluge?generatedServiceId=" . $id);

        $name = $dom->find("#big-aside p.institutionName");
        $institutionLink = $dom->find("#big-aside #btnServices > a");

        if (! isset($name[0]) || ! isset($institutionLink[0])) {
            throw new \Exception("Cannot find institution name or id for service id $id");
        }

        preg_match('/institutionId=([0-9]+)/', $institutionLink->getAttribute('href'), $matches);

        return Institution::firstOrCreate([
            'id_institucije' => $matches[1],
            'naziv' => trim($name[0]->text())
        ]);
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
