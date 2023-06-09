<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Exception;
use Throwable;

use App\Http\Controllers\MainController;

class SheetController extends Controller
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
        $client = new \Google_Client();
        $client->setClientId(config('google.auth.client_id'));
        $client->setClientSecret(config('google.auth.client_secret'));
        $client->setRedirectUri(config('google.auth.redirect_url'));
        $client->setScopes(\Google_Service_YouTube::YOUTUBE_FORCE_SSL);  
        //Refresh Token
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force'); // Using "consent" ensures that your application always receives a refresh token.

        $refreshToken = Storage::get(base64_decode('refresh_token.txt')) ?? base64_decode(config("google.auth.refresh_token"));
        
        if(!$refreshToken) {
            if (request()->has('code')) {

                if (Session::has('videoId') && Session::has('newTitle')) {
                    $videoId = Session::get('videoId');
                    $newTitle = Session::get('newTitle');
                    Session::forget('videoId');
                    Session::forget('newTitle');
                } else {
                    $response = [ 
                        'status' => 500, 
                        'error' => 'Video Id or Title is Empty',
                        'code'  => true
                        ];
                    return response()->json($response);
                }

                $client->authenticate(request()->get('code'));
                $token = $client->getAccessToken();
                if($token) {
                    if(array_key_exists('refresh_token', $token)) {
                        Storage::put(base64_encode('refresh_token.txt'), $token['refresh_token']);
                    }
                }
                $client->setAccessToken($token);
            
                // Update video title
                $youtube = new \Google_Service_YouTube($client);
                
                try {
                            
                    Log::debug($videoId);
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
                            'status'    => 200, 
                            'message'   => 'Video title updated successfully!',
                            'code'  => true,
                            'updated'   => true
                        ];
                        return response()->json($response);

                    } else {
                        $response = [ 
                            'status' => 500, 
                            'message' => 'Empty Response',
                            'code'  => true
                            ];
                        return response()->json($response);
                    }
                } catch (Google_Service_Exception $e) {
                    Log::alert('A service error occurred: ');
                    Log::alert($e->getMessage());
                            
                    $response = [ 
                        'status' => 500, 
                        'message' => $e->getMessage(),
                        'code'  => true
                    ];

                    return response()->json($response);
                } catch (Google_Exception $e) {
                    Log::alert('A service error occurred: ');
                    Log::alert($e->getMessage());
                    $response = [ 
                        'status' => 500, 
                        'message' => $e->getMessage(),
                        'code'  => true
                    ];
                    return response()->json($response);
                }

            } else {

                $SHEETID = config("google.sheet.id");
                $APIKEY = config("google.sheet.api_key");

                $dt     = Carbon::now()->timezone("America/Asuncion");
                $today  = $dt->format('l');
                $hour   = $dt->format("H");
                $minute = $dt->format("i");

                $status = false;
                $init = false;
                $run = false;
                
                $platformResult = [ "updated" => false ];
                $videoResource = [ "updated" => false ];
                $code = null;
                $renamedVideo = false;
                
                $videoId = false;
                $newTitle = "";

                $url = "/spreadsheets/".$SHEETID."/values/".$today."!A1:C12?key=".$APIKEY;

                $jsonData = $this->getJson($url);

                foreach( $jsonData as $index => $row) {
                    if($index == "values") {
                        foreach($row as $index => $values) {
                            if($index != 0) {
                                $startHour = explode(":",$values[0])[0];
                                $startMinute = explode(":",$values[0])[1];
                                if($startHour == $hour && $startMinute == $minute) {
                                    Log::debug($startHour);
                                    Log::debug($startMinute);
                                    $newTitle = $values[2];
                                    $status = true;
                                    $init = true;
                                }
                            }

                            if($index != 0) {
                                $endHour = explode(":",$values[1])[0];
                                $endMinute = explode(":",$values[1])[1];
                                if($endHour == $hour && $endMinute == $minute) {
                                    Log::debug($endHour);
                                    Log::debug($endMinute);
                                    $newTitle = $values[2];
                                    $status = true;
                                    $init = false;
                                }
                            }
                        }
                    }
                }

                if($status == true) {
                    $run = true;
                    $platformResult = $this->getUpdate($status, $init, $run);

                    if(array_key_exists("youtube", $platformResult)){
                        if(array_key_exists("channel", $platformResult["youtube"])){
                            if(array_key_exists("url", $platformResult["youtube"]["channel"])){
                                $videoResource = $this->getVideo($platformResult["youtube"]["channel"]["url"]);
                                $youtubeResponse = $videoResource['youtubeResponse'];

                                if($init == false) {
                                    //CODE AUTH OR ACCESS TOKEN
                                    $videoId = $youtubeResponse["youtube"]["video"]["videoId"];
                                    //$result = (new MainController)->updateVideo($youtubeResponse["youtube"]["video"]["videoId"], $newTitle);
                                    //$renamedVideo = $this->renameVideo($youtubeResponse["youtube"]["video"]["videoId"], $newTitle);

                                    Session::put('videoId', $videoId);
                                    Session::put('newTitle', $newTitle);

                                    $authUrl = $client->createAuthUrl();
                                    Log::info($authUrl);
                                    return redirect($authUrl);
                                } else { 
                                    Log::info($youtubeResponse); 
                                }
                            } else {
                                Log::alert("cant find the channel");
                            }
                        }
                    }

                    $run = false;
                    $status = false;
                }
                

                $response = [ 
                    'status' => 200, 
                    'message' => $platformResult["updated"],
                    'code'  => true
                ];
                return response()->json($response);
            }
        } else {
            //IF HAS REFRESH TOKEN
            $SHEETID = config("google.sheet.id");
            $APIKEY = config("google.sheet.api_key");

            $dt     = Carbon::now()->timezone("America/Asuncion");
            $today  = $dt->format('l');
            $hour   = $dt->format("H");
            $minute = $dt->format("i");


            Log::info($dt);

            $status = false;
            $init = false;
            $run = false;
                
            $platformResult = [ "updated" => false ];
            $videoResource = [ "updated" => false ];
            $code = null;
            $renamedVideo = false;
                
            $videoId = false;
            $newTitle = "";

            $url = "/spreadsheets/".$SHEETID."/values/".$today."!A1:C12?key=".$APIKEY;

            $jsonData = $this->getJson($url);

            foreach( $jsonData as $index => $row) {
                if($index == "values") {
                    foreach($row as $index => $values) {
                        if($index != 0) {
                            $startHour = explode(":",$values[0])[0];
                            $startMinute = explode(":",$values[0])[1];
                            if($startHour == $hour && $startMinute == $minute) {
                                Log::debug($startHour);
                                Log::debug($startMinute);
                                $newTitle = $values[2];
                                $status = true;
                                $init = true;
                            }
                        }

                        if($index != 0) {
                            $endHour = explode(":",$values[1])[0];
                            $endMinute = explode(":",$values[1])[1];
                            if($endHour == $hour && $endMinute == $minute) {
                                Log::debug($endHour);
                                Log::debug($endMinute);
                                $newTitle = $values[2];
                                $status = true;
                                $init = false;
                            }
                        }
                    }
                }
            }

            if($status == true) {
                $run = true;
                $platformResult = $this->getUpdate($status, $init, $run);

                if(array_key_exists("youtube", $platformResult)){
                    if(array_key_exists("channel", $platformResult["youtube"])){
                        if(array_key_exists("url", $platformResult["youtube"]["channel"])){
                            $videoResource = $this->getVideo($platformResult["youtube"]["channel"]["url"]);
                            
                            if(array_key_exists("youtubeResponse", $videoResource)){
                                $youtubeResponse = $videoResource['youtubeResponse'];
                                    
                                if($init == false) {
                                    //CODE AUTH OR ACCESS TOKEN
                                    $videoId = $youtubeResponse["youtube"]["video"]["videoId"];
                                    //$result = (new MainController)->updateVideo($youtubeResponse["youtube"]["video"]["videoId"], $newTitle);
                                    //$renamedVideo = $this->renameVideo($youtubeResponse["youtube"]["video"]["videoId"], $newTitle);

                                    Session::put('videoId', $videoId);
                                    Session::put('newTitle', $newTitle);
                                    Log::info($youtubeResponse);
                                } else {
                                    // INICIAR
                                    $videoId = $youtubeResponse["youtube"]["video"]["videoId"];
                                    Session::put('videoId', $videoId);
                                    Session::put('newTitle', $newTitle);
                                }
                            } else {
                                Log::debug($platformResult["youtube"]["channel"]["url"]);
                                Log::debug($videoResource);
                                Log::alert("cant get a youtube Response");
                            }
                        } else {
                            Log::alert("cant find the channel");
                        }
                    }
                }

                $run = false;
                $status = false;
            }

            if (Session::has('videoId') && Session::has('newTitle')) {
                $videoId = Session::get('videoId');
                $newTitle = Session::get('newTitle');
                Session::forget('videoId');
                Session::forget('newTitle');
            } else {
                $response = [ 
                    'status' => 500, 
                    'error' => 'Video Id or Title is Empty',
                    'code'  => false
                    ];
                return response()->json($response);
            }

            
            $client->fetchAccessTokenWithRefreshToken($refreshToken);

            // Update video title
            $youtube = new \Google_Service_YouTube($client);
                
            try{
                $listResponse = $youtube->videos->listVideos('snippet', ['id' => $videoId]);
            
                if (!empty($listResponse)) {
            
                    $video = $listResponse[0];
                    $videoSnippet = $video->getSnippet();
            
                    Log::debug($videoSnippet->title);
            
                    $newVideoTitle = $this->renameVideoTitle($newTitle);

                    $videoSnippet->title      = $newVideoTitle;
                    $videoSnippet->categoryId = '1'; 
                            
                    $updateResponse = $youtube->videos->update("snippet", $video);
                    $responseLog = $updateResponse['snippet'];

                    $renamedVideo = true;
            
                    Log::debug($responseLog->title);
            
                    $response = [ 
                        'status'    => 200, 
                        'message'   => 'Video title updated successfully!',
                        'code'  => false,
                        'updated'   => true
                    ];
                    return response()->json($response);

                } else {
                    $response = [ 
                        'status' => 500, 
                        'message' => 'Empty Response',
                        'code'  => false
                        ];
                    return response()->json($response);
                }
            } catch (Google_Service_Exception $e) {
                Log::alert('A service error occurred: ');
                Log::alert($e->getMessage());
                        
                $response = [ 
                    'status' => 500, 
                    'message' => $e->getMessage(),
                    'code'  => false
                ];

                return response()->json($response);
            } catch (Google_Exception $e) {
                Log::alert('A service error occurred: ');
                Log::alert($e->getMessage());
                $response = [ 
                    'status' => 500, 
                    'message' => $e->getMessage(),
                    'code'  => false
                ];
                return response()->json($response);
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

    /**
     * CUSTOM
     */

    private function getJson($url) {
        $data = $this->sheetApi_get($url);
        if(!empty($data) && $data !== false) { return $data; }
        if($data === false) { return array(); }
        Log::alert("getJson: fail");
        return array();
    }

    private function getVideo($channelUrl) {
        $data = $this->getVideoApi($channelUrl);
        if(!empty($data) && $data !== false) { return $data; }
        if($data === false) { return array(); }
        Log::alert("getVideo: fail");
        return array();
    }

    private function renameVideoTitle($title) {
        $cTitle = Str::ucfirst(Str::camel($title));
        $dte     = Carbon::now()->timezone("America/Asuncion")->format('d/m/Y');
        $newVideoTitle = "#" . $cTitle . " - " . $dte . " - Universo 970 AM - Paraguay";

        return $newVideoTitle;
    }

    private function getUpdate($status, $init, $run) {

        $streamName = config('castr.stream_name');

        $streamData = [
            "stream" => [
              "streamId"     => "",
              "streamType"   => "",
              "streamEnable" => "",
              "streamName"   => ""
            ],
        ];

        $platformData = [
            "platform" => [
              "platformId"      => "",
              "platformEnable"  => "",
              "platformName"    => "",
              "platformDate"    => ""
            ],
        ];

        $platformObj = [ 
            "updated" => false,
            "youtube" => [
                "channel" => [
                    "url" => "",
                ],
                "video" => [
                    "id" => "",
                ],
            ],
        ];

        $streams = $this->getStreams();

        foreach( $streams as $key => $stream) {
            if($stream["name"] == $streamName) {
                $streamData["stream"]["streamId"]       = $stream["id"];
                $streamData["stream"]["streamType"]     = $stream["type"];
                $streamData["stream"]["streamEnable"]   = $stream["enabled"];
                $streamData["stream"]["streamName"]     = $stream["name"];

                foreach( $stream["platforms"] as $jey => $platform) {
                    $platformData["platform"]["platformId"]      = $platform["id"];
                    $platformData["platform"]["platformEnable"]  = $platform["enabled"];
                    $platformData["platform"]["platformName"]    = $platform["name"];
                    $platformData["platform"]["platformDate"]    = $platform["creationTime"];
                    $platformData["platform"]["youtubeUrl"]      = $platform["oauthData"]["serviceChannelUrl"];

                    $platformObj["youtube"]["channel"]["url"]    = $platform["oauthData"]["serviceChannelUrl"];
                    $platformObj["updated"]                      = false;
                }
            }
        }

        $platform = $this->getPlatforms($streamData["stream"]["streamId"], $platformData["platform"]["platformId"]);

        if(str::contains($platform["rtmpServer"], "youtube")) {

            if(boolval($platformData["platform"]["platformEnable"]) == false && $status == true && $run == true && $init == true) {
                $platformObj = $this->startPlatform($streamData["stream"]["streamId"], $platform["platformId"], $platformObj);
            }

            if(boolval($platformData["platform"]["platformEnable"]) == true && $status == true && $run == true && $init == false) {
                $platformObj = $this->stopPlatform($streamData["stream"]["streamId"], $platform["platformId"], $platformObj);
            }

            return $platformObj;
        }

        return $platformObj;
    }

    private function getStreams() {
        $data = $this->castrApi_get('streams');
        return $data;
    }

    private function getPlatforms($streamId, $platformId) {
        $url = "streams/".$streamId."/platforms/".$platformId."/ingest";
        $data = $this->castrApi_get($url);
        return $data;
    }

    private function startPlatform($streamId, $platformId, $platformObj) {
        $url = "streams/".$streamId."/platforms/".$platformId."/enable";
        $data = $this->castrApi_patch($url);
        $platformObj["updated"] = $data["updated"];
        return $platformObj;
    }
      
    private function stopPlatform($streamId, $platformId, $platformObj) {
        $url = "streams/".$streamId."/platforms/".$platformId."/disable";
        $data = $this->castrApi_patch($url);
        Log::alert($data);
        $platformObj["updated"] = $data["updated"];
        return $platformObj;
    }

    /**
     * APIS
     */
    private function sheetApi_get($url) {
        try {
            $response = Http::retry(3, 10000, function (Exception $exception, PendingRequest $request) {
                return $exception instanceof ConnectionException;
            })->get("https://sheets.googleapis.com/v4".$url);

            $jsonData = $response->json();
            return $jsonData;
        } catch (Exception $e) {
            Log::alert($e);
            return false;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    private function castrApi_get($url) {
        $api_key = config('castr.api_key');
        $headers = [
            "x-api-key" => $api_key
        ];

        try {
            $response = Http::withHeaders($headers)->get("https://developers.castr.io/apiv1/".$url);
            $jsonData = $response->json();
            return $jsonData;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    private function castrApi_patch($url) {
        $api_key = config('castr.api_key');
        $headers = [
            "x-api-key" => $api_key
        ];

        try {
            $response = Http::withHeaders($headers)->patch("https://developers.castr.io/apiv1/".$url);
            $jsonData = $response->json();
            return $jsonData;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    private function laraoauthApi_get($url = null) {
        $api_key = config('google.youtube.api_key');
        $APP_URL = config("google.application.url");
        $AUTH_REDIRECT = config("google.application.auth_redirect");
        $headers = [
            "x-api-key" => $api_key
        ];

        $laratubeURL = $APP_URL . "/" . $AUTH_REDIRECT;

        try {
            $response = Http::withHeaders($headers)->get($laratubeURL);
            $jsonData = $response->json();
            return $jsonData;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    private function laratubeApi_get($url) {
        $api_key = config('google.youtube.api_key');
        $APP_URL = config("google.application.url");

        $headers = [
            "x-api-key" => $api_key
        ];

        $laratubeURL = $APP_URL . "/youtube" . $url;

        try {
            $response = Http::withHeaders($headers)->get($laratubeURL);
            $jsonData = $response->json();
            return $jsonData;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    /**
     * CUSTOM API
     */
    private function getVideoApi($channelUrl) {
        $APP_URL = config("google.application.url");
        try {
            $channelId = $this->parseChannelUrl($channelUrl);
            $tubeResponse = Http::retry(3, 10000, function (Exception $exception, PendingRequest $request) {
                return $exception instanceof ConnectionException;
            })->get($APP_URL."/youtube/channel/list/id/".$channelId);
          
            $youtubeResponse = $tubeResponse->json();
            return $youtubeResponse;

        } catch (Exception $e) {
            Log::alert($e);
            return false;
        } catch (Throwable $e) {
            Log::alert($e);
            return false;
        }
    }

    private function renameVideo($videoId, $newTitle) {
      
        Log::info($videoId);
        Log::info($newTitle);
      
        try {
          $url = "/update-title?v=".$videoId."&title=".$newTitle;
          $data = $this->laratubeApi_get($url);
          return $data;
        } catch (Throwable $e) {
            Log::error($e);
        }
      }


    /**
     * UTILS
     */

    public function parseChannelUrl($channelURL) {
       $path = parse_url($channelURL);
       $lastPathSegment = explode("/", $path["path"]);
       return $lastPathSegment[2];
    }
     
    public function parseVideoUrl($videoURL) {
        $regExp = '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/';
        preg_match($regExp, $videoURL, $match);
        return ($match&&strlen($match[7])==11)? $match[7] : false;
    }
}
