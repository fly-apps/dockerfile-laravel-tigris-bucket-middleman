# Dockerfile-Laravel-Tigris-Bucket-Middleman
This repository connects a Laravel application with Github, and Tigris to:
1. Download artifact from workflow runs.
2. Unzip downloaded artifact.
3. Upload artifact file to a public Tigris bucket.


### Local Installation

1. Install the vendor requirements of the application with:
```
composer install
```

2. Run the server with 
```
php artisan serve
```

3. Please make sure you have a Tigris bucket by following the [steps here.](https://fly.io/docs/reference/tigris/)

4. Save the credentials you'll be receiving from step 1, and add them in your .env file. ( See the env variables added in fly.toml.example OR .env.example! )

5. Make sure you have a Github run artifact created, which you would want to upload to your Tigris bucket. You can make one by simply creating a workflow that uploads a file. [Here's one way!](https://github.com/fly-apps/dockerfile-laravel/pull/38/files#diff-5c3fa597431eda03ac3339ae6bf7f05e1a50d6fc7333679ec38e21b337cb6721R54) 

6. Next, make a request to `upload` with the following parameters:
>run_id - the run id of the workflow used to create the artifact
>name - the name of the file you've indicated during upload from your workflow

7. If all works well, and the upload completes, visit your Tigris bucket at your Tigris console and you should see the artifact( unzipped! ) uploaded in the bucket

