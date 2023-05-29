<?php
// use Google_Client;
// use Google_Service_YouTube;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('/youtube/update-title', function () {
    $client = new Google_Client();
    $client->setClientId(config('google.client_id'));
    $client->setClientSecret(config('google.client_secret'));
    $client->setRedirectUri(config('google.redirect_url'));
    $client->setScopes(Google_Service_YouTube::YOUTUBE_FORCE_SSL);

    if (!request()->has('code')) {
        $authUrl = $client->createAuthUrl();
        return redirect($authUrl);
    } else {
        $client->authenticate(request()->get('code'));
        $token = $client->getAccessToken();
        $client->setAccessToken($token);

        // Update video title
        $youtube = new Google_Service_YouTube($client);

        try{

            $videoId = '4UTl0tnMePc';
            
            $listResponse = $youtube->videos->listVideos('snippet', ['id' => $videoId]);

            if (!empty($listResponse)) {

                $video = $listResponse[0];
                $videoSnippet = $video->getSnippet();

                $tags = $videoSnippet['tags'];
                if (is_null($tags)) {
                    $tags = array("tag1", "tag2");
                } else {
                    array_push($tags, "tag1", "tag2");
                }
                
                $videoSnippet['tags'] = $tags;
                $videoSnippet->setTitle('NEW_VIDEO_TITLE');
                $videoSnippet->setSnippet($videoSnippet);
                
                $updateResponse = $youtube->videos->update("snippet", $video);
                $responseTags = $updateResponse['snippet']['tags'];

                Log::debug($responseTags);

                return 'Video title updated successfully!';
            } else {
                return 'Error';
            }
        } catch (Google_Service_Exception $e) {
            Log::alert('A service error occurred: ', $e->getMessage());
        } catch (Google_Exception $e) {
            Log::alert('A service error occurred: ', $e->getMessage());
        }
        
    }
});
