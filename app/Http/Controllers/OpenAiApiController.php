<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptRequest;
use App\Models\WpPost;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;

class OpenAiApiController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function askAi(PromptRequest $request)
    {
        if ($request->newThread) {
            session()->put("messages", []);
            session()->forget("threadId");
            session()->flash("success", "Conversation reset!");
            return redirect()->route('dashboard');
        }

        $messages = session()->get("messages", []);
        $messages [] = [
            'text' => $request->prompt,
            'source' => 'user'
        ];

        $assistantId = config('assistant_id', 'asst_cKjU5k7pNvSJK016uR8V4RAa');
        $prompt = $request->get('prompt');

        $threadId = session()->get("threadId", null);
        if (!$threadId) {
            //Must start a new conversation
            $response = OpenBaseAI::threads()->createAndRun([
                'assistant_id' => $assistantId,
                'model' => 'gpt-3.5-turbo',
                'thread' => [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                ],
            ]);

            $threadId = $response->threadId;
            $responseId = $response->id;
            session()->put("threadId", $threadId);
            \Log::debug("Thread Response", ['thread' => $response]);
        } else {
            $response = OpenBaseAI::threads()->runs()->create($threadId, [
                'assistant_id' => $assistantId,
                'additional_messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
            ]);
            $responseId = $response->id;
        }
        \Log::debug("Conversation thread id is: {$threadId}.", [
            'run_id' => $responseId,
        ]);

        //Pool the response status
        $time = time();
        do {
            $timeOut = (time() - $time) > config('timeout', 10);
            usleep(400);
            $response = OpenBaseAI::threads()->runs()
                ->retrieve($threadId, $responseId);
            //TODO: We can have a bunch of statuses here!
        } while ($response->status != 'completed' && !$timeOut);

        if ($response->status != 'completed') {
            $messages [] = [
                'text' => "Failed to get an answer from AI. Try to reset the conversation.",
                'source' => 'ai'
            ];
        } else {
            //Fetch the messages from the last run!
            $list = OpenBaseAI::threads()->messages()
                ->list($threadId, [
                    'run_id' => $response->id,
                ]);
            $buffer = '';
            $annotationsFe = [];
            foreach ($list->data as $message) {
                $buffer .= $message->content[0]->text->value;
                $annotations = [];
                $fileNames = [];
                foreach ($message->content[0]->text->annotations as $annotation) {
                    $fileResponse = OpenBaseAI::files()->retrieve($annotation->fileCitation->fileId);
                    $fileNames [$annotation->fileCitation->fileId] = $fileResponse->filename;
                    $annotations [$annotation->text] = $annotation->fileCitation->fileId;
                }
                $postsData = [];
                $postIDs = array_map(fn($val) => explode('-', $val)[1], $fileNames);
                $posts = WpPost::query()->whereIn('id', $postIDs)->with('params')->get();
                foreach ($posts as $post) {
                    $postsData[$post->ID] = [
                        'title' => $post->name,
                        'url' => $post->url,
                    ];
                }

                $index = 0;
                foreach ($annotations as $text => $fileId) {
                    $index++;
                    $annotationBit = " [$index]";
                    $buffer = str_replace($text, $annotationBit, $buffer);

                    $articleId = explode("-", $fileNames[$fileId])[1];
                    $articleTitle =$postsData[$articleId]['title'];
                    $articleUrl = $postsData[$articleId]['url'];
                    $annotationsFe []= [
                        'note' => $annotationBit,
                        'title' => $articleTitle,
                        'url' => $articleUrl,
                    ];
                }
                //TODO: Why are the annotations like 4:0 and 4:8 -- what is the thing after the :
            }
            $messages [] = [
                'text' => $buffer,
                'source' => 'ai',
                'annotations' => $annotationsFe,
            ];
        }

        session()->put("messages", $messages);
        return redirect()->route('dashboard');
    }

}
