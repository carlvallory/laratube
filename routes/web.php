<?php
// use Google_Client;
// use Google_Service_YouTube;
use Illuminate\Support\Facades\Route;

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
        $videoId = '4UTl0tnMePc';
        $video = $youtube->videos->listVideos('snippet', ['id' => $videoId]);
        $videoSnippet = $video[0]->getSnippet();
        $videoSnippet->setTitle('NEW_VIDEO_TITLE');
        $video->setSnippet($videoSnippet);
        $youtube->videos->update('snippet', $video);

        return 'Video title updated successfully!';
    }
});
