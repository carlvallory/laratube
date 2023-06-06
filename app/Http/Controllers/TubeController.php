<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;

class TubeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = [ "return" => true ];
        
        return response()->json($data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $client = new \Google_Client();
        $client->setClientId(config('google.client_id'));
        $client->setClientSecret(config('google.client_secret'));
        $client->setRedirectUri(config('google.redirect_url'));
        $client->setScopes(\Google_Service_YouTube::YOUTUBE_FORCE_SSL);

        $authUrl = $client->createAuthUrl();

        return response()->json($authUrl);
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
    public function show(Request $request, $id)
    {
        $response = $this->getList($request, $id);

        return $response;
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
    
        if (!request()->has('accesstoken')) {
            if (!request()->has('code')) {
                $authUrl = $client->createAuthUrl();
                Log::debug($authUrl);
                return redirect($authUrl);
            } else {
                Log::info(request()->get('code'));
                $client->authenticate(request()->get('code'));
                $token = $client->getAccessToken();
                
            }
        } else {
            $token = request()->get('accesstoken');
        }

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
        
                $response = [ 
                    'status' => 200, 
                    'message' => 'Video title updated successfully!'
                ];
                return response()->json($response);

            } else {
                $response = [ 
                    'status' => 500, 
                    'message' => 'Empty Response'
                    ];
                return response()->json($response);
            }
        } catch (Google_Service_Exception $e) {
            Log::alert('A service error occurred: ');
            Log::alert($e->getMessage());
                    
            $response = [ 
                'status' => 500, 
                'message' => $e->getMessage()
            ];

            return response()->json($response);
        } catch (Google_Exception $e) {
            Log::alert('A service error occurred: ');
            Log::alert($e->getMessage());
            $response = [ 
                'status' => 500, 
                'message' => $e->getMessage()
            ];
            return response()->json($response);
        }

        $response = [ 
            'status' => 404, 
            'message' => 'Not Found'
        ];
        return response()->json($response);
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

    /**
     * CUSTOM
     */

    private function getChannel(Request $request, $id) {
        $API_KEY = config('google.youtube.api_key');
        $CHANNEL_ID = $id;

        $apiURL = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=" . $CHANNEL_ID . "&key=" . $API_KEY;
        $relatedPlaylists = false;

        $responseJson = $this->youtubeApi_get($apiURL);

        if($responseJson["items"]){
            foreach($responseJson["items"] as $key => $item) {
                if($key == 0){
                    $relatedPlaylists = $item["contentDetails"]["relatedPlaylists"];
                }
            }
        

            if($relatedPlaylists) {
                $response = [ 
                    'status' => 200, 
                    'message' => 'Response',
                    'relatedPlaylists'  => $relatedPlaylists
                    ];
                return response()->json($response);
            } else {
                $response = [ 
                    'status'    => 404,
                    'error'     => "not found",
                    'message'   => 'not found'
                    ];
                return response()->json($response);
            }
            
        } else {
            $response = [ 
                'status'    => 500,
                'error'     => $responseJson,
                'message'   => $responseJson
                ];
            return response()->json($response);
        }

        $response = [ 
            'status'    => 404,
            'error'     => "not found",
            'message'   => 'empty'
        ];
        return response()->json($response);
    }

    private function getList(Request $request, $id) {
        $API_KEY = config('google.youtube.api_key');
        $CHANNEL_ID = $request->id;

        $channelResponseJson = $this->getChannel($request, $CHANNEL_ID);
        $channelResponse    = json_decode(json_encode($channelResponseJson->original), true);

        /* $channelResponse = await fetch(NEXTAUTH_URL+"/api/youtube/channel?id="+CHANNEL_ID);
        $channelResponseJson = await channelResponse.json(); */

        Log::debug($channelResponse["relatedPlaylists"]);
        
        $uploads = $channelResponse["relatedPlaylists"]["uploads"];

        $apiURL = "https://www.googleapis.com/youtube/v3/playlistItems?playlistId=".$uploads."&part=snippet,id&chart=mostPopular&key=".$API_KEY;

        $youtubeResponse = [
            "return"    => false,
            "updated"   => false,
            "youtube"   => [
                "video"     => [
                    "channelId"     => "",
                    "title"         => "",
                    "description"   => "",
                    "videoId"       => ""
                ]
            ]
        ];

        $responseJson = $this->youtubeApi_get($apiURL);

        if($responseJson["items"]){
            foreach($responseJson["items"] as $key => $item) {
                if($key == 0){
                    $youtubeResponse["youtube"]["video"]["channelId"]   = $item["snippet"]["channelId"];
                    $youtubeResponse["youtube"]["video"]["title"]       = $item["snippet"]["title"];
                    $youtubeResponse["youtube"]["video"]["description"] = $item["snippet"]["description"];
                    $youtubeResponse["youtube"]["video"]["videoId"]     = $item["snippet"]["resourceId"]["videoId"];
                    $youtubeResponse["updated"] = false;
                    $youtubeResponse["return"]  = true;
                }
            }
            
            if($youtubeResponse["return"]) {
                $response = [ 
                    'status' => 200, 
                    'message' => 'Response',
                    'youtubeResponse'  => $youtubeResponse
                    ];
                return response()->json($response);
            } else {
                $response = [ 
                    'status'    => 404,
                    'error'     => "not found",
                    'message'   => 'not found'
                    ];
                return response()->json($response);
            }
        } else {
            $response = [ 
                'status'    => 500,
                'error'     => $responseJson,
                'message'   => $responseJson
                ];
            return response()->json($response);
        }
    }

    /**
     * API
     */

    private function youtubeApi_get($url) {
        $api_key = config('google.youtube.api_key');
        $headers = [
            "x-api-key" => $api_key
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);
            $jsonData = $response->json();
            return $jsonData;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }
}
