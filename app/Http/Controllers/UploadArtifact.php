<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

use Log;
use ZipArchive;

class UploadArtifact extends Controller
{
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
            // Get help from the custom GithubClient helper we have
            $gH = new \App\Services\GithubClient();

            // Get URL to build artifact of run_id+artifact "name" sent in request
            Log::info( "Retrieving build artifacts '".$request->name."' from run :".$request->run_id );
            $latestArtifactsList = $gH->getLatestBuildsFromGithub( $request->run_id, $request->name );
            $artifact = $latestArtifactsList[0]; 

            // Use the artifact details retrieved from above to Download + Unzip the artifact locally
            Log::info( 'Uploading artifact '.$artifact['name'].' at download uri: '.$artifact['archive_download_url'] );
            $artifactData = $gH->getArtifactFromGithub( $artifact['id'] );
            Storage::disk('local')->put( $artifact['name'].'.zip', $artifactData['body'] );
            $folder = $this->unzip( $artifact['name'].'.zip' );

            // Upload local folder to Tigris, yup! it's s3 compatible, so we can use the s3 driver
            Storage::disk('s3')->writeStream($folder, Storage::disk('local')->readStream($folder));
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
