<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class MainController extends Controller
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
}
