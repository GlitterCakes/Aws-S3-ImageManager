Aws S3 ImageManager
===================

Image Manager for Amazon Web Services S3.
* Open /application/configs/application.ini and set your AWS access key, secret key, and S3 bucket.
* Make sure the /images "directory" in the chosen S3 bucket is available.

Requirements
* PHP Pecl Imagick extension
* PHP Pecl AWS SDK

Side notes
* Works with TinyMCE when a valid file browser callback is configured.
* Able to use as a popup, sending the chosen image back to a form element in the parent window.
