<?php
/**
 * Namespaces
 */
use Aws\S3\S3Client;

/**
 * Application File Management
 * 
 */
class Application_FileManagement
{

	/**
	 * S3 Client
	 * 
	 * Reference to Amazon S3 Client Object
	 * @var S3Client
	 */
	protected $_s3Client;

	/**
	 * Bucket Name
	 * 
	 * The name of the bucket we are working with
	 * @var string Bucket Name
	 */
	protected $_bucketName;
	
	/**
	 * Directory Prefix
	 * 
	 * Prefix to add in front of all file names
	 * @var string Directory path to prefix
	 */
	protected $_directoryPrefix = '';
	
	/**
	 * Constructor
	 * 
	 */
	public function __construct($bucketName)
	{
		// Get config for AWS keys
		$config = Zend_Registry::get('config');
		
		// New AWS S3 Client Object
		$this->_s3Client = S3Client::factory(array(
				'key'	 => $config['app']['aws']['access'],
				'secret' => $config['app']['aws']['secret']
			));

		// Set variables
		$this->_bucketName = $bucketName;
	}

	/**
	 * Get Bucket Name
	 * 
	 * Returns the bucket name currently in use by this object
	 * @return string Bucket Name
	 */
	public function getBucketName()
	{
		return $this->_bucketName;
	}
	
	/**
	 * Set Bucket
	 * 
	 * Sets the currently selected bucket
	 * @param string $bucketName Name of AWS Bucket
	 * @return void
	 */
	public function setBucket($bucketName)
	{
		$this->_bucketName = $bucketName;
	}

	/**
	 * Does Bucket Exist
	 * 
	 * Checks to see if a given bucket name exists and that we can access it
	 * @return boolean True if exists, false on failure.
	 */
	public function bucketExists()
	{
		return $this->_s3Client->doesBucketExist($this->_bucketName, false) ? true : false;
	}

	/**
	 * List Directory Contents
	 * 
	 * Gets the contents of a specific directory
	 * @param string $fullPath The full path to the directory to list contents from
	 * @return array Files / directories in the requested full path
	 */
	public function listDirectoryContents($fullPath)
	{
		// Handle directory prefix
		$fullPath = $this->_directoryPrefix . $fullPath;
		
		// Remove beginning slash
		$fullPath = ltrim($fullPath, '/');

		// Add trailing slash, if we don't already have it
		preg_match('@/$@', $fullPath, $matches);
		if(!count($matches)) {
			$fullPath .= '/';
		}
		
		// Retrieve objects
		$objects = $this->_s3Client->listObjects(array(
			'Bucket' => $this->_bucketName,
			'Prefix' => $fullPath
		));

		// Model data
		$directories = array();
		foreach($objects['Contents'] as $object) {

			// Get key parts and determine if this is a directory or a file
			$keyParts = explode('/', $object['Key']);
			if($keyParts[count($keyParts) - 1] == '') {

				// Create the "directory" if we need it
				if(!array_key_exists($object['Key'], $directories)) {

					// Construct "Directory" path for parent directory
					$key = '';
					for($i = 0; $i < count($keyParts) - 2; $i++) {
						$key .= '/' . $keyParts[$i];
					}

					// Add entry in parent directory for current directory
					$directories[$key][] = array(
						'Name'			 => $keyParts[count($keyParts) - 2],
						'IsDirectory'	 => true
					);

					// Catalog current directory
					$directories['/' . rtrim($object['Key'], '/')] = array();
				}
			}
			else {

				// Construct "Directory" path
				$key = '';
				for($i = 0; $i < count($keyParts) - 1; $i++) {
					$key .= '/' . $keyParts[$i];
				}

				// Store the "File" in our "Directory structure"
				$directories[$key][] = array(
					'Name'			 => $keyParts[count($keyParts) - 1],
					'IsDirectory'	 => false
				);
			}
		}

		// Return modelled data
		return $directories['/' . rtrim($fullPath, '/')];
	}

	/**
	 * Create directory
	 * 
	 * Used to create a directory in S3
	 * @param string $directory Full path of directory
	 * @return boolean true on success, false on failure
	 */
	public function createDirectory($directory)
	{
		// Handle directory prefix
		$directory = $this->_directoryPrefix . $directory;
		
		// Remove beginning slash
		$directory = ltrim($directory, '/') . '/';

		// Verify "directory" doesn't already exist
		if($this->_s3Client->doesObjectExist($this->_bucketName, $directory)) {
			return false;
		}

		// Create "directory"
		$this->_s3Client->putObject(array(
			'Bucket'	=> $this->_bucketName,
			'Key'		=> $directory,
			'Body'		=>	''
		));

		return true;
	}

	/**
	 * Add File
	 * 
	 * Adds a file to S3
	 * @param string $source The source file (full path)
	 * @param string $target The target file (full path)
	 * @return void
	 */
	public function addFile($source, $target)
	{
		// Add leading slash, if we didn't previously and we need it.
		if(!empty($this->_directoryPrefix) && $target[0] != '/') {
			$target = '/' . $target;
		}
		
		// Handle directory prefix
		$target = $this->_directoryPrefix . $target;
		
		// Get the mime type of the file
		$finfo	 = new finfo(FILEINFO_MIME_TYPE);
		$mime	 = $finfo->file($source) != '' ? $finfo->file($source) : 'application/octet-stream';

		// Store the file in S3
		$this->_s3Client->putObject(array(
			'Bucket'		 => $this->_bucketName,
			'Key'			 => $target,
			'ContentType'	 => $mime,
			'Body'			 => fopen($source, 'r')
		));
	}

	/**
	 * Delete Item
	 * 
	 * Deletes an item from S3
	 * @param string $file Full path of file
	 * @return void
	 */
	public function deleteItem($file)
	{
		// Add leading slash, if we didn't previously and we need it.
		if(!empty($this->_directoryPrefix) && $file[0] != '/') {
			$file = '/' . $file;
		}
		
		// Handle directory prefix
		$file = $this->_directoryPrefix . $file;
		
		// Get objects for deletion
		$objects = $this->_s3Client->listObjects(array(
			'Bucket' => $this->_bucketName,
			'Prefix' => ltrim($file, '/')
		));
		
		// Catalog items to delete
		if (empty($objects['Contents'])) {
			return NULL;
		}
		
		$results = array();
		foreach($objects['Contents'] as $file) {
			$results[] = array(
				'Key' => $file['Key']
			);
		}
		// Execute mass delete
		$this->_s3Client->deleteObjects(array(
			'Bucket'	 => $this->_bucketName,
			'Objects'	 => $results
		));
	}

	/**
	 * Rename Item
	 * 
	 * Used to rename a directory or file
	 * @param string $oldFileName Full path for old filename
	 * @param string $newFileName Full path for new filename
	 * @throws NotImplementedException Always, This function is not yet implemented
	 */
	public function renameItem($oldFileName, $newFileName)
	{
		// Add leading slash, if we didn't previously and we need it.
		if(!empty($this->_directoryPrefix)) {
			
			if($oldFileName[0] != '/') {
				$oldFileName = '/' . $oldFileName;
			}

			if($newFileName[0] != '/') {
				$newFileName = '/' . $newFileName;
			}
		}
		
		// Handle directory prefixes
		$oldFileName = $this->_directoryPrefix . $oldFileName;
		$newFileName = $this->_directoryPrefix . $newFileName;
		
		// Remove beginning slashes
		$oldFileName = ltrim($oldFileName, '/');
		$newFileName = ltrim($newFileName, '/');

		// If we aren't renaming for real, just get outa here.
		if($oldFileName == $newFileName) {
			return;
		}

		// Copy the object
		$this->_s3Client->copyObject(array(
			'Bucket'	 => $this->_bucketName,
			'Key'		 => $newFileName,
			'CopySource' => $this->_bucketName . '/' . $oldFileName
		));

		// Delete the old one
		$this->_s3Client->deleteObject(array(
			'Bucket' => $this->_bucketName,
			'Key'	 => $oldFileName
		));
	}
}