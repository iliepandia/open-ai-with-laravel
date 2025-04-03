<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptRequest;
use App\Models\WpPost;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

class OpenAiApiController extends Controller
{

    protected function resetThread()
    {
        session()->put("messages", []);
        session()->forget("threadId");
        session()->flash("success", "Conversation reset!");
    }

    protected function waitForAiResponse($threadId, $responseId ) : \OpenAI\Responses\Threads\Runs\ThreadRunResponse
    {
        //Pool the response status
        $time = time();
        do {
            $timeOut = (time() - $time) > config('ai_wait_timeout', 10);
            usleep(config('poll_sleep_time', 400));
            $response = OpenBaseAI::threads()->runs()
                ->retrieve($threadId, $responseId);
            //TODO: We can have a bunch of statuses here!
        } while ($response->status != 'completed' && !$timeOut);

        return $response;

    }

    protected function getTextMessageFromAI(ThreadMessageListResponse $list)
    {
        $buffer = '';
        foreach ($list->data as $message) {
            $buffer .= $message->content[0]->text->value;
        }
        return $buffer;
    }

    protected function getReferencedArticles( array $annotations )
    {
        \Log::debug( "Annotations", ['annotations'=>$annotations] );

        $referencedArticles = [];
        $postIDs = [];
        foreach($annotations as $text => $fileName ){
            $postIDs[$text] = explode('-', $fileName)[1];
        }
        \Log::debug( "PostID", ['postIds'=>$postIDs] );
        //Load the links from the web
        //TODO - make this more abstract so it can be reused
        $posts = WpPost::query()->whereIn('ID', $postIDs)->with('params')->get();
        foreach ($annotations as $text => $fileName ) {
            $postID = $postIDs[$text];
            $referencedArticles[$text] = [
                'title' => $posts->where('ID', $postID)->first()->name,
                'url' => $posts->where('ID', $postID)->first()->url,
            ];
        }

        return $referencedArticles;
    }
    protected function getAnnotationsFromAI(ThreadMessageListResponse $list) : array
    {
        $formattedAnnotations = [];
        foreach ($list->data as $message) {
            $annotations = [];

            //Link annotation text to file names...
            foreach ($message->content[0]->text->annotations as $annotation) {
                if($annotation->fileCitation?->fileId){
                    //TODO: We should be caching these...
                    $fileResponse = OpenBaseAI::files()->retrieve($annotation->fileCitation->fileId);
                    if(!str_starts_with($fileResponse->filename, "article-")){
                        //TODO: A more abstract resolver/validator - now we assume that the relevant file starts with this string
                        continue;
                    }
                    $annotations[$annotation->text] = $fileResponse->filename;
                }
            }

            $referencedArticles  = $this->getReferencedArticles($annotations);

            $noteIndex = 0;
            foreach ($annotations as $text => $fileId) {
                $noteIndex++;
                $annotationText = " [$noteIndex]";

                $articleTitle =$referencedArticles[$text]['title']??$fileId;
                $articleUrl = $referencedArticles[$text]['url']??'--missing--';
                $formattedAnnotations []= [
                    'original_text' => $text,
                    'note' => $annotationText,
                    'title' => $articleTitle,
                    'url' => $articleUrl,
                ];
            }
            //TODO: Why are the annotations like 4:0 and 4:8 -- what is the thing after the :
        }

        return $formattedAnnotations;
    }
    protected function getResponseFromAI(PromptRequest $request){
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

        //Pool the response status
        $response = $this->waitForAiResponse($threadId,$responseId);

        if ($response->status != 'completed') {
            \Log::error( "Failed to get an answer from ai.", ['response' => $response] );
            return [
                'text' => "Failed to get an answer from AI. Try to reset the conversation.",
                'source' => 'ai'
            ];
        }

        //Fetch the messages from the last run!
        $list = OpenBaseAI::threads()->messages()
            ->list($threadId, [
                'run_id' => $response->id,
            ]);

        //AI Text Response
        $message = $this->getTextMessageFromAI($list);

        $annotations = $this->getAnnotationsFromAI($list);

        foreach($annotations as $annotation){
            //TODO: we need a more abstract way to do this
            $message = str_replace( $annotation['original_text'], $annotation['note'], $message );
        }

        return  [
            'text' => $message,
            'source' => 'ai',
            'annotations' => $annotations,
        ];
    }
    /**
     * Store a newly created resource in storage.
     */
    public function askAi(PromptRequest $request)
    {
        if ($request->newThread) {
            $this->resetThread();
            return redirect()->route('dashboard');
        }

        $messages = session()->get("messages", []);

        //Add the user message to the list...
        $messages [] = [
            'text' => $request->prompt,
            'source' => 'user'
        ];

        //Add the AI message to the list...
        $messages []= $this->getResponseFromAI($request);

        session()->put("messages", $messages);
        return redirect()->route('dashboard');
    }

}
