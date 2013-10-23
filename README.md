Aws S3 ImageManager
===================

Image Manager for Amazon Web Services S3. Uses a responsive design image grid style layout to display images for a given "folder" in an S3 bucket. Also resizes images in to various optimized sizes to use in responsive layouts.

Instructions
* Open /application/configs/application.ini and set your AWS access key, secret key, and S3 bucket.
* Make sure the /images "directory" in the chosen S3 bucket is available.

Requirements
* PHP Pecl Imagick extension
* PHP AWS SDK Phar

Side notes
* Works with TinyMCE when a valid file browser callback is configured.
* Able to use as a popup, sending the chosen image back to a form element in the parent window.
