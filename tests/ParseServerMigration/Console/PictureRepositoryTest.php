<?php

namespace ParseServerMigration\Console;

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use MongoDB\Client;
use Parse\ParseClient;
use Parse\ParseFile;
use Parse\ParseObject;
use Aws\S3\S3Client;
use ParseServerMigration\Config;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class PictureRepositoryTest extends TestCase
{
    /**
     * @var S3Client | ObjectProphecy
     */
    private $s3Client;

    /**
     * @var Client | ObjectProphecy
     */
    private $mongoDbClient;

    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @var ParseObject | ObjectProphecy
     */
    private $picture;

    protected function setUp()
    {
        ParseClient::initialize(Config::PARSE_APP_ID, Config::PARSE_REST_KEY, Config::PARSE_MASTER_KEY);
        ParseClient::setServerURL(Config::PARSE_FS_URL,'parse');

        $this->s3Client = $this->prophesize('Aws\S3\S3Client');
        $this->mongoDbClient = $this->prophesize('MongoDB\Client');
        $this->pictureRepository = new PictureRepository($this->s3Client->reveal(), $this->mongoDbClient->reveal());

        $image = ParseFile::_createFromServer(
            'tfss-d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg',
            'https://sofresh-bucket-recette.s3.amazonaws.com/pictures/d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg'
        );

        $this->picture =  $this->prophesize('Parse\ParseObject');
        $this->picture->get(Argument::type('string'))->willReturn($image);
    }

    public function testRenameImageCleanUpPictureNameAndReturnAnUpdateResult()
    {
        $collection = $this->prophesize('MongoDB\Collection');
        $updateResult = $this->prophesize('MongoDB\UpdateResult');

        $updateResult->getMatchedCount()->willReturn(1);

        //We actually test name clean up here as a lot of underlying implementation at the same time but...
        $collection->updateOne(
            ['image' => 'tfss-d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg'],
            ['$set' => ['image' => 'd6a07886620d4ee58df9a824a34af8bephoto_profile.jpg']]
        )->willReturn($updateResult);

        $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::MONGO_PICTURES_TABLE_NAME)->willReturn($collection);

        $actualUpdateResult = $this->pictureRepository->renameImage($this->picture->reveal());

        $this->assertSame($updateResult->reveal(), $actualUpdateResult);
    }

    public function testRenameThumbnailCleanUpPictureNameAndReturnAnUpdateResult()
    {
        $collection = $this->prophesize('MongoDB\Collection');
        $updateResult = $this->prophesize('MongoDB\UpdateResult');

        $updateResult->getMatchedCount()->willReturn(1);
        $collection->updateOne(
            ['thumbnail' => 'tfss-d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg'],
            ['$set' => ['thumbnail' => 'd6a07886620d4ee58df9a824a34af8bephoto_profile.jpg']]
        )->willReturn($updateResult);

        $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::MONGO_PICTURES_TABLE_NAME)->willReturn($collection);

        $actualUpdateResult = $this->pictureRepository->renameThumbnail($this->picture->reveal());

        $this->assertSame($updateResult->reveal(), $actualUpdateResult);
    }

    public function testUploadPictureReturnAnArray()
    {
        $this->s3Client->putObject(Argument::type('array'))->willReturn(new Result());
        $result = $this->pictureRepository->uploadImage($this->picture->reveal());

        $this->assertSame(array(), $result);
    }

    public function testDeletePictureReturnAnArray()
    {
        $this->s3Client->deleteObject(Argument::type('array'))->willReturn(new Result());
        $result = $this->pictureRepository->deletePicture($this->picture->reveal());

        $this->assertSame(array(), $result);
    }

    /**
     * @expectedException \Aws\S3\Exception\S3Exception
     */
    public function testDeletePictureThrow()
    {
        $command = $this->prophesize('Aws\CommandInterface');
        $this->s3Client->deleteObject(Argument::type('array'))->willThrow(new S3Exception(
            '',
            $command->reveal()
        ));

        $this->pictureRepository->deletePicture($this->picture->reveal());

    }

    //It should retrieve a list of all pictures
    //It should parse pictures and rename it by a given batch size
    //It should upload only pictures that have been updated in mongo to S3 by a given batch size
    public function testMigrateAllPictures()
    {
    }
}
