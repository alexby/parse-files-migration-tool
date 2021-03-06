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
     * @throws \Exception
     *
     * @return \Parse\ParseObject[]|\Generator
     */
    public function findAllImages(int $limit, bool $orderDesc = false) : \Generator
    {
        $query = new ParseQuery(Config::PARSE_FILES_CLASS_NAME);
        if ($orderDesc) {
            $query->descending('createdAt');
        } else {
            $query->ascending('createdAt');
        }
        $found = false;
        do {
            /** @var ParseObject[] $result */
            if (isset($result)) {
                if ($orderDesc) {
                    $query->lessThan('createdAt', $result[count($result) - 1]->getCreatedAt());
                } else {
                    $query->greaterThan('createdAt', $result[count($result) - 1]->getCreatedAt());
                }
            }
            $result = $query
                ->limit($limit)
                ->exists(Config::PARSE_FILES_FIELD_NAME)
                ->notEqualTo(Config::PARSE_FILES_FIELD_NAME, null)
                ->find();
            if (count($result) > 0) {
                $found = true;
                $limit -= count($result);
                yield $result;
            }
        } while((count($result) > 0) && ($limit > 0));
        if ((count($result) === 0) && (!$found)) {
            throw new \Exception(
                'Can not find any record '.Config::PARSE_FILES_CLASS_NAME.':'.Config::PARSE_FILES_FIELD_NAME.'.'
                .' The error could be in class (absentee collection)'
                .' or in field (absentee document with such field)'
            );
        }
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
        $originalFileName = $picture->get(Config::PARSE_FILES_FIELD_NAME)->getName();

        $updateResult = $this->renamePicture($originalFileName, Config::PARSE_FILES_FIELD_NAME);

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
        $originalFileName = $picture->get(Config::PARSE_FILES_THUMBNAIL_FIELD_NAME)->getName();

        $updateResult = $this->renamePicture($originalFileName, Config::PARSE_FILES_THUMBNAIL_FIELD_NAME);

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
        $imageUrl = $picture->get(Config::PARSE_FILES_FIELD_NAME)->getURL();

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
        $imageUrl = $picture->get(Config::PARSE_FILES_THUMBNAIL_FIELD_NAME)->getURL();

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
            'ContentType' => Config::PARSE_FILES_CONTENT_TYPE,
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
            'Key' => Config::S3_UPLOAD_FOLDER.'/'.$this->getFileNameFromUrl($picture->get(Config::PARSE_FILES_FIELD_NAME)->getURL()),
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

        $fstream = fopen($url, 'r');

        if ($fstream !== false) {
            return new Stream($fstream);
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
            Config::PARSE_FILES_FIELD_NAME => $this->getFileNameFromUrl($picture->get(Config::PARSE_FILES_FIELD_NAME)->getName()),
        );
    }
}
