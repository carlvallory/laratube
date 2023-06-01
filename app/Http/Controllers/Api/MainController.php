<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\MainResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class MainController extends Controller
{
    public function index(Request $request)
    {
        $array = [ "return" => true ];
        
        return MainResource::collection($array);
    }

    public function update(Request $request)
    {
        if (request()->has('v') && request()->has('title')) {
            $videoId    = request()->get('v');
            $newTitle   = request()->get('title');

            Session::put('videoId', $videoId);
            Session::put('newTitle', $newTitle);
        } else {
            if (Session::has('videoId')) {
                $videoId = Session::get('videoId');
            }
            if (Session::has('newTitle')) {
                $newTitle = Session::get('newTitle');
            }
            Log::alert("No videoID or NewTitle");
            Log::debug($videoId);
        }

        $client = new \Google_Client();
        $client->setClientId(config('google.client_id'));
        $client->setClientSecret(config('google.client_secret'));
        $client->setRedirectUri(config('google.redirect_url'));
        $client->setScopes(\Google_Service_YouTube::YOUTUBE_FORCE_SSL);
    
        if (!request()->has('code')) {
            $authUrl = $client->createAuthUrl();
            return redirect($authUrl);
        } else {
            $client->authenticate(request()->get('code'));
            $token = $client->getAccessToken();
            $client->setAccessToken($token);
    
            // Update video title
            $youtube = new \Google_Service_YouTube($client);
    
            try{
                
                $listResponse = $youtube->videos->listVideos('snippet', ['id' => $videoId]);
    
                if (!empty($listResponse)) {
    
                    $video = $listResponse[0];
                    $videoSnippet = $video->getSnippet();
    
                    Log::debug($videoSnippet->title);
    
                    $videoSnippet->title      = $newTitle;
                    $videoSnippet->categoryId = '1'; 
                    
                    $updateResponse = $youtube->videos->update("snippet", $video);
                    $responseLog = $updateResponse['snippet'];
    
                    Log::debug($responseLog->title);
    
                    $status = 200;
                    $message = 'Video title updated successfully!';
                    $response = [ $status, $message ];
                    return MainResource::collection($response);
                } else {
                    $status = 500;
                    $message = 'Empty Response';
                    $response = [ $status, $message ];
                    return MainResource::collection($response);
                }

            } catch (Google_Service_Exception $e) {
                Log::alert('A service error occurred: ');
                Log::alert($e->getMessage());
                $status = 500;
                $message = $e->getMessage();
                $response = [ $status, $message ];
                return MainResource::collection($response);

            } catch (Google_Exception $e) {
                Log::alert('A service error occurred: ');
                Log::alert($e->getMessage());
                $status = 500;
                $message = $e->getMessage();
                $response = [ $status, $message ];
                return MainResource::collection($response);
            }
            
        }
        
        $status = 404;
        $message = 'Not Found';
        $response = [ $status, $message ];
        return MainResource::collection($response);
    }
}
