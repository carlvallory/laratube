<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!request()->has('v') && !request()->has('title')) {
            $videoId    = request()->get('v');
            $newTitle   = request()->get('title');

            $request->session()->put('videoId', $videoId);
            $request->session()->put('newTitle', $newTitle);
        } else {
            if ($request->session()->has('videoId')) {
                $videoId = $request->session()->get('videoId');
            }
            if ($request->session()->has('newTitle')) {
                $newTitle = $request->session()->get('newTitle');
            }
            Log::alert("No videoID or NewTitle");
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
        
                        return 'Video title updated successfully!';
                    } else {
                        return 'Error';
                    }
                } catch (Google_Service_Exception $e) {
                    Log::alert('A service error occurred: ');
                    Log::alert($e->getMessage());
                } catch (Google_Exception $e) {
                    Log::alert('A service error occurred: ');
                    Log::alert($e->getMessage());
                }
                
            }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
