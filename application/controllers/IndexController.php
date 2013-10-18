<?php
/**
 * Index controller
 * 
 */
class IndexController extends Zend_Controller_Action
{
	/**
	 * S3 Client
	 * 
	 * Holds an instance to the AWS s3 client
	 * @var Brilliant_Global_Amazon_S3_FileManagement_Site
	 */
	protected $_s3Client;
	
	/**
	 * Allowed Formats
	 * 
	 * Formats that a user is allowed to upload with file extensions to map to.
	 * @var type array (Format Constant => file extension)
	 */
	protected $allowedFormats = array(
		'GIF'	=>	'.gif',
		'JPEG'	=>	'.jpg',
		'PNG'	=>	'.png',
		'BMP'	=>	'.bmp'
	);
	
	/**
	 * Composite Image Dimensions
	 * 
	 * Images will be squared up with a white background filling extra space
	 * @var array List of image dimensions
	 */
	protected $compositeImageDimensions = array(
		array(
			'width'		=>	64,
			'height'	=>	64
		),
		array(
			'width'		=>	140,
			'height'	=>	140
		)
	);
	
	/**
	 * Image Widths
	 * 
	 * Images we want to resize by width, maintaing aspect ratio
	 * @var array List of image widths
	 */
	protected $imageWidths = array(
		'300',
		'320',
		'480',
		'768',
		'980',
		'1240'
	);
	
	/**
	 * Initializes things
	 * 
	 * @throws Zend_Exception When S3 bucket doesn't exist
	 * @return void
	 */
    public function init()
    {
		// Retrieve app config
		$config = Zend_Registry::get('config');
		
		// Use the file manager object for S3
		$this->_s3Client = new Application_FileManagement($config['app']['aws']['s3']['imageBucketName']);

		// Verify S3 bucket exists before doing anything
		if(!$this->_s3Client->bucketExists()) {
			throw new Zend_Exception('CDN Bucket "' . $config['app']['aws']['s3']['imageBucketName'] . '" in S3 does not yet exist, please create it and try again.');
		}
    }

	/**
	 * Get Requested Path
	 * 
	 * Securely creates a full path from user inputted directory
	 * @return type 
	 */
	protected function getRequestedPath($dirAppend = '')
	{
		// Construct actual directory
		$directory = ltrim($this->_request->getParam('directory'), '/');
		return sprintf('images/photos%s' . ($directory == '/' ? '' : '/' . $directory), $dirAppend);
	}
	
	/**
	 * Generate Safe Filename
	 * 
	 * Generates a safe file name, removes things we don't allow.
	 * @param string $file The filename to filter
	 * @return string Filtered filename
	 */
	protected function generateSafeFilename($file)
	{
		return preg_replace('/\-+/', '-', preg_replace('/[^a-zA-Z0-9\-_]/', '-', preg_replace('/\.[^\.]*$/', '', $file)));
	}
	
	/**
	 * Index action
	 * 
	 * The default page load action, displays the image manager
	 * @return void
	 */
    public function indexAction()
    {
        // Add required JS for signup
		$this->view->headScript()->appendFile('/site/image-manager.js');
		
		// Pass data to view
		$this->view->config = array(
			'outputImageLocation'	=>	'http://' . $this->_s3Client->getBucketName() . '/images/photos',
			'imageLocation'			=>	'http://' . $this->_s3Client->getBucketName() . '/images/photos-300',
			'mce'					=>	$this->_request->getParam('mce', false) ? true : false,
			'eleId'					=>	$this->_request->getParam('ele-id', false) ? $this->_request->getParam('ele-id', false) : false
		);
    }
	
	/**
	 * List Action
	 * 
	 * Returns directory contents
	 * @return void
	 */
	public function listAction()
	{	
		// Get full requested path
		$fullPath = $this->getRequestedPath();
		
		// If we are using S3 as our CDN, retrieve files from there instead
		$results = array();
		
		// Get directory listing
		$directories = $this->_s3Client->listDirectoryContents($fullPath);
		foreach($directories as $directory) {
			$results[] = array(
				'path'			=>	'/' . $directory['Name'],
				'isDirectory'	=>	$directory['IsDirectory']
			);
		}
		
		// Output results
		$this->_helper->json(array(
			'status'	=>	'success',
			'results'	=>	$results
		));
	}
	
	/**
	 * Create Directory Action
	 * 
	 * Used to create a directory
	 * @return void 
	 */
	public function createDirectoryAction()
	{
		// Composite image directories
		foreach($this->compositeImageDimensions as $dimension) {
			$newPath = $this->getRequestedPath('-' . $dimension['width'] . 'x' . $dimension['height']) . '/' . basename($this->generateSafeFilename($this->_request->getParam('name')));
			if(!$this->_s3Client->createDirectory($newPath)) {
				$this->_helper->json(array(
					'status'		=>	'error',
					'errors'		=>	array('An error occurred loading parent working directory, please try again later or contact support.')
				));
			}
		}
		
		// Width image directories
		foreach($this->imageWidths as $width) {
			$newPath = $this->getRequestedPath('-' . $width) . '/' . basename($this->generateSafeFilename($this->_request->getParam('name')));
			if(!$this->_s3Client->createDirectory($newPath)) {
				$this->_helper->json(array(
					'status'		=>	'error',
					'errors'		=>	array('An error occurred loading parent working directory, please try again later or contact support.')
				));
			}
		}
		
		// Source photos directory
		$newPath = $this->getRequestedPath() . '/' . basename($this->generateSafeFilename($this->_request->getParam('name')));
		if(!$this->_s3Client->createDirectory($newPath)) {
			$this->_helper->json(array(
				'status'		=>	'error',
				'errors'		=>	array('An error occurred loading parent working directory, please try again later or contact support.')
			));
		}
		
		// Output results
		$this->_helper->json(array(
			'status'		=>	'success'
		));
	}
	
	/**
	 * Delete Action
	 * 
	 * Used to delete a file or directory
	 * @return void
	 */
	public function deleteAction()
	{	
		// Process deletion for composite photo directories
		foreach($this->compositeImageDimensions as $dimension) {
			$this->_s3Client->deleteItem($this->getRequestedPath('-' . $dimension['width'] . 'x' . $dimension['height']));
		}
		
		// Process deletion for width photo directories
		foreach($this->imageWidths as $width) {
			$this->_s3Client->deleteItem($this->getRequestedPath('-' . $width));
		}
		
		// Delete the source photo
		$this->_s3Client->deleteItem($this->getRequestedPath());
		
		// Output results
		$this->_helper->json(array(
			'status'		=>	'success'
		));
	}
	
	/**
	 * Upload Action
	 * 
	 * Used to upload a new file
	 * @return void
	 */
	public function uploadAction()
	{	
		// If we don't have a file upload and we tried loading this page...
		if(!array_key_exists('Filedata', $_FILES)) {
			throw new Zend_Exception('No file upload detected');
		}
			
		// If we had an error, stop right here.
		if($_FILES['Filedata']['error'][0] !== UPLOAD_ERR_OK) {
			$this->_helper->json(array(
				'status'		=>	'error',
				'errors'		=>	array('Error uploading file with name "' .  $_FILES['Filedata']['name'][0] . '"')
			));
		}

		// Verify that the file is a file upload (security measure)
		if(!is_uploaded_file($_FILES['Filedata']['tmp_name'][0])) {
			throw new Zend_Exception('WARNING! Invalid file upload detected!');
		}

		// Attempt to load up ImageMagick
		try {
			$i = new Imagick($_FILES['Filedata']['tmp_name'][0]);
			
			// Verify image format
			if(!in_array($i->getimageformat(), array_keys($this->allowedFormats))) {
				$this->_helper->json(array(
					'status'		=>	'error',
					'errors'		=>	array('Error, invalid image format detected with name "' .  $_FILES['Filedata']['name'][0] . '"')
				));
			}
		}
		catch(ImagickException $e) {
			$this->_helper->json(array(
				'status'		=>	'error',
				'errors'		=>	array('Error, invalid image detected with name "' .  $_FILES['Filedata']['name'][0] . '"')
			));
		}

		// Retrieve sanitized version of the filename to use
		$target = $this->getRequestedPath() . '/' . $this->generateSafeFilename($_FILES['Filedata']['name'][0]) . $this->allowedFormats[$i->getimageformat()];

		// Store the source image as-is
		$this->_s3Client->addFile($_FILES['Filedata']['tmp_name'][0], $target);

		// Resize image for various widths
		foreach($this->imageWidths as $width) {

			// Attempt to load up ImageMagick
			try {
				$i = new Imagick($_FILES['Filedata']['tmp_name'][0]);
			}
			catch(ImagickException $e) {
				$this->_helper->json(array(
					'status'		=>	'error',
					'errors'		=>	array('Error, invalid image detected with name "' .  $_FILES['Filedata']['name'][0] . '"')
				));
			}

			// Get geometry of input image
			$imageGeometry = $i->getimagegeometry();

			// Set the target
			$target = $this->getRequestedPath('-' . $width) . '/' . $this->generateSafeFilename($_FILES['Filedata']['name'][0]) . $this->allowedFormats[$i->getimageformat()];
			
			// Shrink down the image if we need to
			if($imageGeometry['width'] > $width) {

				// Perform resize
				$resizeHeight = round(($imageGeometry['height'] * $width) / $imageGeometry['width']);
				$i->resizeimage($width, $resizeHeight, imagick::FILTER_LANCZOS, 1);

				// Save new image
				$outFile = '/tmp/image-resizer-' . uniqid();
				$i->writeimage($outFile);

				// Upload to S3
				$this->_s3Client->addFile($outFile, $target);
			}
			else {
				$outFile = '/tmp/image-resizer-' . uniqid();
				$i->writeimage($outFile);

				// Upload to S3
				$this->_s3Client->addFile($_FILES['Filedata']['tmp_name'][0], $target);
			}
		}

		// Create squared of thumbnails with whitespace filling the gaps
		foreach($this->compositeImageDimensions as $dimension) {

			// Attempt to load up ImageMagick
			try {
				$i = new Imagick($_FILES['Filedata']['tmp_name'][0]);
			}
			catch(ImagickException $e) {
				$this->_helper->json(array(
					'status'		=>	'error',
					'errors'		=>	array('Error, invalid image detected with name "' .  $_FILES['Filedata']['name'][0] . '"')
				));
			}

			// Resize image
			$i->resizeimage($dimension['width'], $dimension['height'], imagick::FILTER_LANCZOS, 1, true);

			// Create a new image to place the existing image on top of (square this off)
			$baseImage = new Imagick();
			$baseImage->newimage($dimension['width'], $dimension['height'], new ImagickPixel('white'));
			$baseImage->setformat($i->getimageformat());

			// Overlay image and flatten layers
			$baseImage->compositeimage($i, imagick::COMPOSITE_DEFAULT, (($baseImage->getImageWidth() - ($i->getImageWidth()))/2), ((($baseImage->getImageHeight()) - ($i->getImageHeight()))/2));
			$baseImage->flattenimages();

			// Save the new image
			$outFile = '/tmp/image-resizer-' . uniqid();
			$baseImage->writeimage($outFile);

			// Upload to S3
			$target = $this->getRequestedPath('-' . $dimension['width'] . 'x' . $dimension['height']) . '/' . $this->generateSafeFilename($_FILES['Filedata']['name'][0]) . $this->allowedFormats[$i->getimageformat()];
			$this->_s3Client->addFile($outFile, $target);
		}
		
		// Output results
		$this->_helper->json(array(
			'status'	=>	'success'
		));
	}
}

