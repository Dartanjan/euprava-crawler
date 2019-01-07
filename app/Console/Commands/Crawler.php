<?php

namespace App\Console\Commands;

use App\CrawlJob;
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
        dispatch(new CrawlJob);
    }
}
