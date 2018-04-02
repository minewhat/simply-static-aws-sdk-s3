<?php
namespace Simply_Static;

require '/home/ubuntu/vendor/autoload.php';
use Aws\Exception\AwsException;
require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

class Transfer_Files_To_S3_Task extends Task {

	/**
	 * @var string
	 */
	protected static $task_name = 'transfer_files_to_s3';

	public function perform() {
		$status = $this->upload_to_s3();

		if ( $status == true ) {
            $message = __( 'Pushed to S3 Successfully: ', 'simply-static' );
            $this->save_status_message( $message );
            return true;
        } else {
            $message = __( 'Push to S3 Failed: ', 'simply-static' );
            $this->save_status_message( $message );
		}
	}

	/**
	 * Upload the archive folder to S3.
	 * @return Status
	 */
	public function upload_to_s3() {
		$archive_dir = $this->options->get_archive_dir();
        $aws_bucket =  $this->options->get( 'aws_s3_bucket' );
        $aws_key = $this->options->get( 'aws_access_key_id' );
        $aws_secret = $this->options->get( 'aws_secret_access_key' );
        Util::debug_log( "aws: ". $aws_bucket . $aws_key . $aws_secret);

        // Create an S3 client
        $client = new \Aws\S3\S3Client([
            'region'  => 'eu-central-1',
            'version' => '2006-03-01',
            'credentials' => [
                'key'    => $aws_key,
                'secret' => $aws_secret,
            ],
        ]);

        // Where the files will be source from
        $source = $archive_dir;

        // Where the files will be transferred to
        $dest = 's3://'. $aws_bucket;

        //Create a transfer object.
        try {
            $manager = new \Aws\S3\Transfer($client, $source, $dest,[
                'before' => function (\Aws\Command $command) {
                    // Commands can vary for multipart uploads, so check which command
                    // is being processed
                    if (in_array($command->getName(), ['PutObject', 'CreateMultipartUpload'])) {
                        // Set custom cache-control metadata
                        $command['CacheControl'] = 'max-age=72000';
                        // Apply a canned ACL
                        $command['ACL'] = 'public-read';
                    }
                },
            ]);

            // Perform the transfer synchronously.
            $manager->transfer();
        } catch (AwsException $e) {
            // output error message if fails
            return new WP_Error( 'cannot_publish_to_s3', sprintf( __( "Could not publish folder to S3: %s: %s",$e->getMessage(), $source ) ));
        }
        return true;
	}
}
