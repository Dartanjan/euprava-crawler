<?php

namespace App;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use PHPHtmlParser\Dom;

class CrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle()
    {
        $newServices = $this->getNewServices();

        if ($newServices->isEmpty()) {
            info("No new or updated services found.");

            return;
        }

        $messages = $newServices->map(function ($service) {
            return sprintf(
                "・ %s (%s) (%s)",
                $service->naziv,
                $service->e_usluga ? "e-usluga" : "nije e-usluga",
                $service->url()
            );
        });

        info("New or changed items:", $messages->toArray());

        return $this->notify($messages);

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
        $links = $dom->find('#main #content ul li > a');
        $newServices = collect();

        foreach($links->toArray() as $link) {
            preg_match('/generatedServiceId=([0-9]*)/', $link->getAttribute('href'), $serviceMatches);

            if (! isset($serviceMatches[1])) {
                continue;
            }

            $serviceId = $serviceMatches[1];

            $item = Service::firstOrNew([
                'id_usluge' => $serviceId,
                'naziv' => trim(str_replace('"', "'", $link->lastChild()->text)),
                'e_usluga' => strpos($link->innerHtml(), '(e-usluga)') !== false,
                'dokument' => strpos($link->innerHtml(), '(ima dokument)') !== false,
                'privreda' => strpos($link->innerHtml(), '(pravna lica)') !== false,
                'zakazivanje' => strpos($link->innerHtml(), '(e-zakazivanje)') !== false,
            ]);

            // New service found
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

        // If for some reason name/id cannot be found
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
