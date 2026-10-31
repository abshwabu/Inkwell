<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate {--path= : Custom output path for sitemap.xml}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and write sitemap.xml for search engines';

    /**
     * Execute the console command.
     */
    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Generating sitemap.xml...');

        $customPath = $this->option('path');
        $outputPath = $sitemapService->generateFile($customPath);

        $this->info("Sitemap generated successfully at: {$outputPath}");

        return Command::SUCCESS;
    }
}
