<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;
use OpenAI\Responses\Chat\CreateStreamedResponseChoice;

class OpenAiAssistantTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assistant';

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
//        $response = OpenBaseAI::threads()->createAndRun([
//            'assistant_id' => 'asst_cKjU5k7pNvSJK016uR8V4RAa',
//            'model' => 'gpt-3.5-turbo',
//            'thread' => [
//                'messages' => [
//                    [
//                        'role' => 'user',
//                        'content' => 'what is ascension?',
//                    ]
//                ],
//            ],
//        ]);
//
//        dump($response);

//        $response = OpenBaseAI::threads()->runs()->retrieve('thread_Wj8nrf7HmR2VzCGgcAxwBAWM','run_iAZR0NjCItPyNERf0am8QFKl');
//
//        dd($response);

//        dump(OpenBaseAI::threads()->messages()->list('thread_Wj8nrf7HmR2VzCGgcAxwBAWM'));

//        dump(OpenBaseAI::threads()->runs()->list('thread_Wj8nrf7HmR2VzCGgcAxwBAWM'));

//        $response = OpenBaseAI::threads()->runs()->create('thread_Wj8nrf7HmR2VzCGgcAxwBAWM', [
//            'assistant_id' => 'asst_cKjU5k7pNvSJK016uR8V4RAa',
//            'additional_messages' => [
//                [
//                    'role' => 'user',
//                    'content' => 'tell me more about fear processing'
//                ]
//            ],
//        ]);
//
//        dump($response);


//        $response = OpenBaseAI::threads()->runs()->retrieve('thread_Wj8nrf7HmR2VzCGgcAxwBAWM','run_0Qlfi1eBxmVSSeWw0VZLGfm7');
//        dump($response);


//          dump(OpenBaseAI::threads()->messages()->list('thread_Wj8nrf7HmR2VzCGgcAxwBAWM'));


//        $response = OpenBaseAI::threads()->runs()->create('thread_Wj8nrf7HmR2VzCGgcAxwBAWM', [
//            'assistant_id' => 'asst_cKjU5k7pNvSJK016uR8V4RAa',
//            'additional_messages' => [
//                [
//                    'role' => 'user',
//                    'content' => 'what is oneness? and how do I get there?'
//                ]
//            ],
//        ]);
//
//        dump($response);

//        $response = OpenBaseAI::threads()->runs()->retrieve('thread_Wj8nrf7HmR2VzCGgcAxwBAWM','run_yBFoi0DIAu3izfOXFtOFmu8o');
//        dump($response);


          dump(OpenBaseAI::threads()->messages()->list('thread_Wj8nrf7HmR2VzCGgcAxwBAWM', [
              'run_id' => 'run_yBFoi0DIAu3izfOXFtOFmu8o'
          ]));


    }
}
