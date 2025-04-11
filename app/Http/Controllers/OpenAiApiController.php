<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromptRequest;
use App\Models\Conversation;
use App\Models\OpenAiMessage;
use App\Models\WpPost;
use App\Services\OpenAi\OpenAiApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI as OpenBaseAI;
use OpenAI\Resources\Batches;
use OpenAI\Responses\Threads\Messages\ThreadMessageListResponse;

class OpenAiApiController extends Controller
{

    protected function resetThread()
    {
        session()->forget("threadId");
        session()->flash("success", "Conversation reset!");
    }

    protected function getToolResult( $name, $args ){
        if($name == 'list_all_the_products'){
            return [
                [
                    "url" => "https://ineliabenz.com/fear-processing",
                    "price" => "9.99",
                    "title" => "Fear Processing Exercise",
                    "description" => "Exercise to process your fear"
                ],
                [
                    "url" => "https://ineliabenz.com/telepathy",
                    "price" => "21.99",
                    "title" => "Experiential Telepathy",
                    "description" => "Develop your psychic abilities. Communicate with the world around you using only your mind."
                ],
                [
                    "url" => "https://ineliabenz.com/ascension",
                    "price" => "990",
                    "title" => "Ascension Course",
                    "description" => "A course that will provide ascension information for the user."
                ],
            ];
        }
        return [
            'error' => 'true',
            'message' => 'no information available for the requested params',
        ];
    }
    protected function handleToolExecution($response) : array
    {
        if ( $response['status'] != 'requires_action' ||
            ($response['required_action']['type']??false) != 'submit_tool_outputs' ){
            //nothing to do
            return $response;
        }

        //Let's call all the tools and build the output results
        $outputs = [];
        foreach( $response['required_action']['submit_tool_outputs']['tool_calls'] as $toolCall ){
            $functionName = $toolCall['function']['name'];
            $arguments =  $toolCall['function']['arguments'];
            $output = $this->getToolResult($functionName,$arguments);
            OpenAiMessage::create([
                'assistant_id' => $response['assistant_id'],
                'type' => 'tool_call',
                'thread_id' => $response['thread_id'],
                'run_id' => $response['id'],
                'raw_response' => '',
                'metadata' => json_encode([
                    "tool_call" => [
                        "function" => $functionName,
                        "arguments" => $arguments,
                        "output" => $output,
                    ],
                ], JSON_PRETTY_PRINT),
                'prompt' => '',
            ]);

            $outputs []= [
                'tool_call_id' => $toolCall['id'],
                'output' => json_encode($output, JSON_PRETTY_PRINT),
            ];
        }

        if(empty($output)){
            return $response;
        }

        $ai = new OpenAiApi();
        return $ai->threads_runs_submit_tool_outputs(
            threadId:  $response['thread_id'],
            runId: $response['id'],
            outputs: $outputs
        );
    }

    protected function waitForAiResponse($threadId, $responseId ) : array
    {
        $ai = new OpenAiApi();
        //Pool the response status
        $time = time();
        do {
            $timeOut = (time() - $time) > config('app.ai_wait_timeout', 10);
            usleep(config('app.poll_sleep_time', 400));

            $response = $ai->threads_runs_retrieve($threadId,$responseId);

            $response = $this->handleToolExecution($response);

            //TODO: We can have a bunch of statuses here!
        } while ($response['status'] != 'completed' && !$timeOut);

        return $response;

    }

    protected function getTextMessageFromAI(array $list)
    {
        $buffer = '';
        foreach ($list['data'] as $message) {
            $buffer .= $message['content'][0]['text']['value'];
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

    protected function getReferencedProducts( array $annotations )
    {
        \Log::debug( "Product Annotations", ['annotations'=>$annotations] );

        $referencedProducts = [];
        foreach ($annotations as $text => $fileName ) {
            $data = Storage::disk('local')->get("products/{$fileName}");
            if($data){
                $data = json_decode($data, true);
            }
            if(!$data) continue;
            $post = WpPost::query()->where('ID', $data['wp_post_id'])->with('params')->first();
            $referencedProducts[$text] = [
                'title' => "Class: " . $post->name,
                'url' => $post->url,
            ];
        }

        return $referencedProducts;
    }
    protected function getAnnotationsFromAI(array $messageResponse) : array
    {
        $ai = new OpenAiApi();
        $formattedAnnotations = [];
        foreach ($messageResponse['data'] as $message) {
            $annotations = [];
            $productAnnotations = [];

            //Link annotation text to file names...
            foreach ($message['content'][0]['text']['annotations'] as $annotation) {
                if($annotation['file_citation']['file_id']??null){
                    //TODO: We should be caching these...
                    $fileName = $ai->files_get_file_name($annotation['file_citation']['file_id']);
                    //TODO: A more abstract resolver/validator - now we assume that the relevant file starts with this string
                    if(str_starts_with($fileName, "article-")){
                        $annotations[$annotation['text']] = $fileName;
                        continue;
                    }
                    if(str_starts_with($fileName, "product-")){
                        $productAnnotations[$annotation['text']] = $fileName;
                    }
                }
            }

            $referencedArticles  = $this->getReferencedArticles($annotations);
            $referencedProducts =  $this->getReferencedProducts($productAnnotations);

            $noteIndex = 0;
            foreach ($annotations as $text => $fileName) {
                $noteIndex++;
                $annotationText = " [$noteIndex]";

                $articleTitle =$referencedArticles[$text]['title']??$fileName;
                $articleUrl = $referencedArticles[$text]['url']??'--missing--';
                $formattedAnnotations []= [
                    'original_text' => $text,
                    'note' => $annotationText,
                    'title' => $articleTitle,
                    'url' => $articleUrl,
                ];
            }

            foreach($productAnnotations as $text => $fileName ){
                $noteIndex++;
                $annotationText = " [$noteIndex]";

                $productTitle =$referencedProducts[$text]['title']??$fileName;
                $productUrl = $referencedProducts[$text]['url']??'--missing--';
                $formattedAnnotations []= [
                    'original_text' => $text,
                    'note' => $annotationText,
                    'title' => $productTitle,
                    'url' => $productUrl,
                ];
            }
            //TODO: Why are the annotations like 4:0 and 4:8 -- what is the thing after the :
        }

        return $formattedAnnotations;
    }
    protected function getResponseFromAI(PromptRequest $request){
        $assistantId = config('assistant_id', config('app.open_ai_agent_id'));
        $prompt = $request->get('prompt');
        $threadId = session()->get("threadId", null);
        $ai = new OpenAiApi();

        if (!$threadId) {
            //Must start a new conversation
            $response = $ai->threads_create_and_run([
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

            $runId = $response['id'];
            $threadId = $response['thread_id'];
            session()->put("threadId", $threadId);
            OpenAiMessage::create([
                'assistant_id' => $assistantId,
                'type' => 'new_thread',
                'thread_id' => $threadId,
                'run_id' => $runId,
                'raw_response' => json_encode($response, JSON_PRETTY_PRINT),
                'prompt' => $prompt,
            ]);
        } else {
            $response = $ai->threads_runs_create($threadId, [
                'assistant_id' => $assistantId,
                'additional_messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ]
                ],
            ]);
            $runId = $response['id'];
            OpenAiMessage::create([
                'assistant_id' => $assistantId,
                'type' => 'update_thread',
                'thread_id' => $threadId,
                'run_id' => $runId,
                'raw_response' => json_encode($response, JSON_PRETTY_PRINT),
                'prompt' => $prompt,
            ]);
        }

        //Pool the response status
        $response = $this->waitForAiResponse($threadId, $runId);

        OpenAiMessage::create([
            'assistant_id' => $assistantId,
            'type' => 'ai_response',
            'thread_id' => $threadId,
            'run_id' => $runId,
            'raw_response' => json_encode($response, JSON_PRETTY_PRINT),
            'prompt' => $prompt,
        ]);

        if ($response['status'] != 'completed') {
            \Log::error( "Failed to get an answer from ai.", ['response' => $response] );
            return [
                'text' => "Failed to get an answer from AI. Try to reset the conversation. Status was [{$response['status']}].",
                'source' => 'ai',
                'assistant_id' => $assistantId,
                'thread_id' => $threadId,
                'run_id' => $runId,
            ];
        }

        //Fetch the messages from the last run!
        $messageResponse = $ai->threads_messages_list($threadId, [
            'run_id' => $runId,
        ]);

        //AI Text Response
        $message = $this->getTextMessageFromAI($messageResponse);

        $annotations = $this->getAnnotationsFromAI($messageResponse);

        OpenAiMessage::create([
            'assistant_id' => $assistantId,
            'type' => 'message_retrieved',
            'thread_id' => $threadId,
            'run_id' => $runId,
            'raw_response' => json_encode($response, JSON_PRETTY_PRINT),
            'metadata' => json_encode($annotations, JSON_PRETTY_PRINT),
            'prompt' => $prompt,
        ]);

        foreach($annotations as $annotation){
            //TODO: we need a more abstract way to do this
            $message = str_replace( $annotation['original_text'], $annotation['note'], $message );
        }

        return  [
            'text' => $message,
            'source' => 'ai',
            'annotations' => $annotations,
            'assistant_id' => $assistantId,
            'thread_id' => $threadId,
            'run_id' => $runId,
        ];
    }
    public function voteMessage(Request $request)
    {
        $conversation = Conversation::where('id', $request->get('id') )->firstOrFail();
        $conversation->feedback = $request->get('up');
        $conversation->save();
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
        $aiResponse = $this->getResponseFromAI($request);

        //Add the user message to the list...
        Conversation::create([
            'user_id' => $request->user()->id,
            'message' => $request->prompt,
            'source' => 'user',
            'assistant_id' => $aiResponse['assistant_id'],
            'thread_id' => $aiResponse['thread_id'],
            'run_id' => $aiResponse['run_id'],
        ]);

        //Add the AI message to the list...
        Conversation::create([
            'user_id' => $request->user()->id,
            'message' => $aiResponse['text']??null,
            'annotations' => json_encode($aiResponse['annotations']??null, JSON_PRETTY_PRINT),
            'source' => 'ai',
            'assistant_id' => $aiResponse['assistant_id'],
            'thread_id' => $aiResponse['thread_id'],
            'run_id' => $aiResponse['run_id'],
        ]);

        return redirect()->route('dashboard');
    }

    public function tests()
    {
        return "playground";
    }
}
