<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;
use OpenAI\Responses\Chat\CreateStreamedResponseChoice;

class OpenAiListFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:open-ai-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = OpenBaseAI::files()->list();
        foreach($response->data as $file) {
            dump($file->filename);
        }
    }
}
