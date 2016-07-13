<?php

namespace ParseServerMigration\Console;

use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Stream;
use MongoDB\Collection;
use Parse\ParseObject;
use Aws\S3\S3Client;
use Parse\ParseQuery;
use ParseServerMigration\Config;
use MongoDB\Client;

/**
 * Class PictureUploader
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class PictureRepository
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var Client
     */
    private $mongoDbClient;

    /**
     * @param S3Client $s3Client
     * @param Client $mongoDbClient
     */
    public function __construct(S3Client $s3Client, Client $mongoDbClient)
    {
        $this->s3Client = $s3Client;
        $this->mongoDbClient = $mongoDbClient;
    }

    /**
     * @param ParseObject $picture
     *
     * @return \MongoDB\UpdateResult
     *
     * @throws \Exception
     */
    public function renamePicture(ParseObject $picture)
    {
        $originalFileName = $picture->get('image')->getName();
        $formattedFileName = $this->getFileNameFromUrl($originalFileName);

        /** @var Collection $collection */
        $collection = $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PICTURES_TABLE_NAME);

        $updateResult = $collection->updateOne(
            ['image' => $originalFileName],
            ['$set' => ['image' => $formattedFileName]]
        );

        if ($updateResult->getMatchedCount() != 1) {
            throw new \Exception('No document with picture : ['.$picture->get('image')->getName().'] found');
        }

        return $updateResult;
    }

    /**
     * @param ParseObject $picture
     * @return array
     *
     * @throws \Exception
     */
    public function uploadPicture(ParseObject $picture)
    {
        $stream = new CachingStream($this->getFileStream($picture->get('image')->getURL()));

        $result = $this->s3Client->putObject([
            'Bucket' => Config::S3_BUCKET,
            'Key'    => Config::S3_UPLOAD_FOLDER.'/'.$this->getFileNameFromUrl($picture->get('image')->getURL()),
            'Body'   => $stream,
            'ContentType' => 'image/jpeg',
            'ACL'    => 'public-read',
        ]);

        return $result->toArray();
    }

    /**
     * @param ParseObject $picture
     *
     * @return array
     */
    public function deletePicture(ParseObject $picture)
    {
        $result = $this->s3Client->deleteObject(array(
            'Bucket' => Config::S3_BUCKET,
            'Key'    => Config::S3_UPLOAD_FOLDER.'/'.$this->getFileNameFromUrl($picture->get('image')->getURL())
        ));

        return $result->toArray();
    }

    /**
     * This will actually read from Parse server and insert data into a given MongoDB database
     *
     * @return \MongoDB\Driver\WriteResult
     *
     * @throws \Exception
     */
    public function migrateAllPictures()
    {
        /** @var Collection $collection */
        $collection = $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PICTURES_TABLE_NAME);

        $query = new ParseQuery(Config::PICTURES_TABLE_NAME);

        $objects = [];

        $query->each(function (ParseObject $picture) use ($objects) {
            $objects[] = $this->buildDocumentFromParseObject($picture);
        });

        $insertResult = $collection->insertMany($objects);

        if ($insertResult->getInsertedCount()) {
            throw new \Exception('An error occurred when inserting document into mongoDB');
        }

        return $insertResult;
    }


    //Below methods should probably be extracted to dedicated components
    /**
     * @param string $url
     *
     * @return string
     */
    private function getFileNameFromUrl(string $url)
    {
        $url = explode('/', $url);
        $fileName = end($url);
        $cleanFileName = str_replace('-', '', str_replace('tfss', '', $fileName));

        return $cleanFileName;
    }

    /**
     * @param string $url
     *
     * @return Stream
     *
     * @throws \ErrorException
     */
    private function getFileStream(string $url)
    {
        $url = str_replace('invalid-file-key', Config::PROD_FILE_KEY, $url);

        if (@fopen($url, 'r')) {
            return new Stream(fopen($url, 'r'));
        }

        $error = error_get_last();
        throw new \ErrorException($error['message']);
    }

    /**
     * @param ParseObject $picture
     *
     * @return array
     */
    private function buildDocumentFromParseObject(ParseObject $picture)
    {
        return array(
            'image' => $this->getFileNameFromUrl($picture->get('image')->getName())
        );
    }
}
