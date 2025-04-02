<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;
use OpenAI\Responses\Chat\CreateStreamedResponseChoice;

class OpenAiTestOne extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:open-ai-test-one';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'The initial playground for open AI';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $buffer = "";
        $files = \Storage::disk('local')->allFiles("/products");
        //files = array_slice($files, 0, 1);
        foreach($files as $file){
            $buffer .= \Storage::disk('local')->get($file);
        }

        $question = "I want to improve my relationships.";

        //TODO: It kind of seems stupid to pass this every single time!!!
        //This will have 61k, tokens!!!
        $prompt = "Here is a list of products in JSON format:\n\n{$buffer}\n\n From this list select two products that would help with this question:\n\n{$question}\n\nOutput the product names and why they are good fit.";

        dump($prompt);
//        $result = OpenBaseAI::chat()
//            ->create([
//            //'model' => 'gpt-3.5-turbo',
//            'model' => 'o1-mini', // << too slow!
//            //'model' => 'gpt-4',  //<< context too small!
//            'messages' => [
//                ['role' => 'user', 'content' => $prompt ],
//            ],
//        ]);
//        dump($result->choices[0]->message->content);
//        dump($result->usage);

       $streamedResult = OpenBaseAI::chat()->createStreamed([
            //'model' => 'gpt-3.5-turbo',
            'model' => 'o1-mini',
            //'model' => 'gpt-4',  //<< context too small!
            'messages' => [
                ['role' => 'user', 'content' => $prompt ],
            ],
        ]);

       foreach( $streamedResult->getIterator() as $response ){
           /** @var CreateStreamedResponseChoice $choice */
           $choice = $response->choices[0];
           echo($choice->delta->content);
       }

    }
}
