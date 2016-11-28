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
 * Class PictureUploader.
 *
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
     * @param Client   $mongoDbClient
     */
    public function __construct(S3Client $s3Client, Client $mongoDbClient)
    {
        $this->s3Client = $s3Client;
        $this->mongoDbClient = $mongoDbClient;
    }

    /**
     * @param $limit
     * @param bool $orderDesc
     *
     * @return \Parse\ParseObject[]
     */
    public function findAllImages(int $limit, bool $orderDesc = false) : array
    {
        $query = new ParseQuery(Config::PARSE_FILES_CLASS_NAME);
        if ($orderDesc) {
            $query->descending('createdAt');
        }

        return $query->limit($limit)->find();
    }

    /**
     * @param ParseObject $picture
     *
     * @return \MongoDB\UpdateResult
     *
     * @throws \Exception
     */
    public function renameImage(ParseObject $picture)
    {
        $originalFileName = $picture->get('image')->getName();

        $updateResult = $this->renamePicture($originalFileName, 'image');

        return $updateResult;
    }

    /**
     * @param ParseObject $picture
     *
     * @return \MongoDB\UpdateResult
     *
     * @throws \Exception
     */
    public function renameThumbnail(ParseObject $picture)
    {
        $originalFileName = $picture->get('thumbnail')->getName();

        $updateResult = $this->renamePicture($originalFileName, 'thumbnail');

        return $updateResult;
    }

    /**
     * @param string $originalFileName
     * @param string $fieldName
     *
     * @return \MongoDB\UpdateResult
     */
    private function renamePicture(string $originalFileName, string $fieldName)
    {
        $formattedFileName = $this->getFileNameFromUrl($originalFileName);

        /** @var Collection $collection */
        $collection = $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PARSE_FILES_CLASS_NAME);

        $updateResult = $collection->updateOne(
            [$fieldName => $originalFileName],
            ['$set' => [$fieldName => $formattedFileName]]
        );

        return $updateResult;
    }

    /**
     * @param ParseObject $picture
     *
     * @return array
     *
     * @throws \Exception
     */
    public function uploadImage(ParseObject $picture)
    {
        $imageUrl = $picture->get('image')->getURL();

        return $this->uploadPicture($imageUrl);
    }

    /**
     * @param ParseObject $picture
     *
     * @return array
     *
     * @throws \Exception
     */
    public function uploadThumbnail(ParseObject $picture)
    {
        $imageUrl = $picture->get('thumbnail')->getURL();

        return $this->uploadPicture($imageUrl);
    }

    /**
     * @param string $imageUrl
     *
     * @return array
     *
     * @throws \Exception
     */
    private function uploadPicture(string $imageUrl)
    {
        $stream = new CachingStream($this->getFileStream($imageUrl));

        $result = $this->s3Client->putObject([
            'Bucket' => Config::S3_BUCKET,
            'Key' => Config::S3_UPLOAD_FOLDER.'/'.$this->getFileNameFromUrl($imageUrl),
            'Body' => $stream,
            'ContentType' => 'image/jpeg',
            'ACL' => 'public-read',
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
            'Key' => Config::S3_UPLOAD_FOLDER.'/'.$this->getFileNameFromUrl($picture->get('image')->getURL()),
        ));

        return $result->toArray();
    }

    /**
     * This will actually read from Parse server and insert data into a given MongoDB database.
     *
     * @return \MongoDB\InsertManyResult
     *
     * @throws \Exception
     */
    public function migrateAllPictures()
    {
        /** @var Collection $collection */
        $collection = $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PARSE_FILES_CLASS_NAME);

        $query = new ParseQuery(Config::PARSE_FILES_CLASS_NAME);

        $objects = [];

        $query->each(function (ParseObject $picture) use ($objects) {
            $objects[] = $this->buildDocumentFromParseObject($picture);
        });

        return $collection->insertMany($objects);
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
        $url = str_replace('invalid-file-key', Config::PARSE_FILE_KEY, $url);

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
            'image' => $this->getFileNameFromUrl($picture->get('image')->getName()),
        );
    }
}
