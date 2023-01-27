<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Helpers\Paths;
use Auth, Log;
use App\Helpers\Keys;
use Illuminate\Http\Request;

class CopyWriterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('subscription');
    }

    public function getAiResults(Request $request){
        try{
            $response = $this->getOpenAIResults($request);
            if($response['error']){
                return response()->json([
                    'message' => $response['message'],
                    'status' => 'Failed',
                    'choices' => [],
                ], 400);
            }
            $choices = $response['choices'];
            return response()->json([
                'message' => 'Generated',
                'status' => 'Success',
                'choices' => $choices,
            ]);
        }catch(\Exception $error){
            \Log::info('CopyWriterController@getAiResults error message: ' . $error->getMessage());
            $message = "Unable to complete request.";
            return response()->json(['message' => $message], 500);
        }
    }

    public function getOpenAIResults($request){
        $client = new \GuzzleHttp\Client();
        $url = "https://api.openai.com/v1/engines/text-davinci-002/completions";
        
        $temperature = $this->getTemperature($request->creativity);
        $prompt = $this->getPrompt($request);
        if($this->contentModerationisFlagged($prompt)){
            return [
                'error' => true,
                'choices' => [],
                'message' => 'Request was found promoting sexual, hateful, violent, or self-harm content. Please try again with a different content.'
            ];
        }
        $res = $client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . Keys::OPEN_AI_KEY,
            ],
            'json' => [
                'prompt' => $prompt,
                'temperature' => $temperature,
                'max_tokens' => 1000,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
                'n' => (int) $request->variants,
                "user"=> 'user'.Auth::id()
            ], 
    
        ]);
        $contents = $res->getBody()->getContents();
        $contents = json_decode($contents);
        $responseChoices = $contents->choices;

        return [
            'error' => false,
            'choices' => $responseChoices
        ];
        
        return $responseChoices;
    }

    private function contentModerationisFlagged($text){
        $body = [
            'input' => $text,
        ];

        $client = new \GuzzleHttp\Client();
        $url ='https://api.openai.com/v1/moderations';
        $res = $client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . Keys::OPEN_AI_KEY,
            ],
            'json' => $body,
        ]);
        $contents = $res->getBody()->getContents();
        $response = json_decode($contents);
        return $response->results[0]->flagged;
    }

    private function getPrompt(Request $request)
    {
        $language = $request->language != 'english' ? " Translate the result to $request->language." : '';
        $keyPoints = $request->keywords ?? '';

        switch ($request->objective) {
            case 'Audiobook':
                
                return <<<PROMPT
                    Write a creative audio book using a $request->tone tone. Use the following key points.$language
                    
                    Key points: $keyPoints
                PROMPT;
                break;
            case 'Long Video Sales Script':

                return <<<PROMPT
                    Write a long video sales script using a $request->tone tone. Use the following key points.$language
                    
                    Key points: $keyPoints
                PROMPT;
                break;
            case 'Facebook Video Ads Script':
                
                return <<<PROMPT
                    Write a creative Facebook video ads script to run on Facebook using a $request->tone tone. Use the following key points.$language:
                    
                    Key points: $keyPoints
                    
                PROMPT;
                break;
            case 'Instagram Video Ads Script':

                return <<<PROMPT
                    Write a creative Instagram video ads script to run on Instagram using a $request->tone tone. Use the following key points.$language:
                    
                    Key points: $keyPoints
                
                PROMPT;
                break;
            case 'YouTube Video Ads Script':

                return <<<PROMPT
                    Write a creative YouTube video ads script to run on YouTube using a $request->tone tone. Use the following key points.$language:
                        
                    Key points: $keyPoints
                PROMPT;
                break;

            case 'TV Commercials':

                return <<<PROMPT
                    Write a creative TV commercial using a $request->tone tone. Use the following key points.$language:
                        
                    Key points: $keyPoints
                PROMPT;
                break;

            case 'Radio Advert':

                return <<<PROMPT
                    Write a creative Radio advert script using a $request->tone tone. Use the following key points.$language
                    
                    Key points: $keyPoints
                PROMPT;
                break;

            case 'Podcast':

                return <<<PROMPT
                    Write a creative Podcast script using a $request->tone tone. Use the following key points.$language
                    
                    Key points: $keyPoints
                PROMPT;
                break;

            case 'Tiktok Video Ads Script':
                // $samples = CopywriterSample::where('type', 'email')->inRandomOrder()->limit(1)->get()
                //     ->map(fn ($sample) => 'Message: ' . $sample->content)->join("\n");

                return <<<PROMPT
                    Write a creative Tiktok video ads script to run on Tiktok using a $request->tone tone. Use the following key points.$language:
                        
                    Key points: $keyPoints
                PROMPT;
                break;
            default:
                throw new RuntimeErrorException('Context not implemented');
        }
    }

    private function getTemperature(string $creativity)
    {
        switch ($creativity) {
            case 'optimal':
                return 0.7;
            case 'low':
                return 0.2;
            case 'medium':
                return 0.5;
            case 'high':
                return 0.9;
            case 'max':
                return 1;
            default:
                return 0;
        }
    }
}
