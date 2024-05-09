<?php
namespace App\Services;

use Config;
use GuzzleHttp\Client;
use Log;

class GithubClient
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

}