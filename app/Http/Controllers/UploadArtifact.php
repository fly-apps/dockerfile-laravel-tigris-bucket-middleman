<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

use GuzzleHttp\Client;
use Config;
use Log;
use File;
use ZipArchive;

class UploadArtifact extends Controller
{

    /**
     * Make a call to the github api for repo-artifact communication
     */
    function ghClient( )
    {
        $appConfig = Config::get('app'); 
          
        $token = $appConfig['github_token'];
        $headers = [
            'Authorization' => 'Bearer ' . $token,        
            'Accept'        => 'application/vnd.github+json',
        ];

        $client = new Client([
            'base_uri' => "https://api.github.com/repos/fly-apps/dockerfile-laravel/actions/",
            'headers'  => $headers
        ]);

        return $client; 
    }


    /**
     * Get a list of build artifacts for the dockerfile-laravel package from the calling workflow "build" run id and the name of the artifact
     */
    function getLatestBuildsFromGithub( $runId, $name )
    {
        $href = "runs/$runId/artifacts?name=".$name;
    
        $client = $this->ghClient();
        $response = $client->request('GET', $href);
    
        $list = json_decode( 
            $response->getBody()->getContents(), 
            1
        );
        return $list['artifacts'];
    }

    /**
     * Get file contents from github artifact through artifact_id
     */
    function getArtifactFromGithub( $artifactId )
    {
        Log::info( 'Retrieving artifact '.$artifactId );
        $client = $this->ghClient();
        $href = "artifacts/$artifactId/zip";
        $response = $client->request('GET', $href );

        return [
            'body'=>$response->getBody(),
            'headers'=>$response->getHeaders()
        ];
    }

    /**
     * Get filename from github artifact response
     */
    function getFileNameFromResponseHeader( $header )
    {
        $disposition = $header["Content-Disposition"][0];
        $fileName    = trim( (explode( 'filename="', $disposition )[1]), '"' );
        return $fileName; 
    }

    
    /**
     * THE ENTRYPOINT
     * 
     * Method 1: Download + Upload only one file
     * @param run_id
     * @param name
     */
    public function upload( Request $request )
    {
        try{
            Log::info( "Retrieving build artifacts '".$request->name."' from run :".$request->run_id );
            $latestArtifactsList = $this->getLatestBuildsFromGithub( $request->run_id, $request->name );
            $artifact = $latestArtifactsList[0]; 

            // Download + Unzip locally
            Log::info( 'Uploading artifact '.$artifact['name'].' at download uri: '.$artifact['archive_download_url'] );
            $artifactData = $this->getArtifactFromGithub( $artifact['id'] );
            Storage::disk('local')->put( $artifact['name'].'.zip', $artifactData['body'] );
            $folder = $this->unzip( $artifact['name'].'.zip' );

            // Upload folder to Tigris
            Storage::disk('s3')->writeStream($folder, Storage::disk('local')->readStream($folder));
            //Storage::disk('local')->delete($folder);

            Log::info( 'Completed upload...' );
            return response('Success', 200);
        }catch(\Exception $e){
            Log::info( "Encountered error: ". $e->getMessage() );
            return response('Error encountered!', 500);
        }
    }

    /**
     * Return path to folder where zip contents was sent to 
     */
    public function unzip( $fileName )
    {
        $path = Storage::path($fileName);
        $folderName = trim($fileName, ".zip");
       
        $zip = new ZipArchive;
        Log::info( "opening path:".$path);
        if ($zip->open($path) === TRUE) {
            Log::info( "zip file found, unzipping...");
            $extractPath = Storage::path($folderName);
            $zip->extractTo($extractPath);
            $zip->close();

            // Get the file from the folder created
            $files = Storage::disk('local')->files($folderName);
            return $files[0];            
        } else {
            Log::info("failed to open zip file");
            return false;
        }
    }
}
