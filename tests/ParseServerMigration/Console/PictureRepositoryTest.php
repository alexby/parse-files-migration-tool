<?php

namespace ParseServerMigration\Console;

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use MongoDB\Client;
use MongoDB\InsertManyResult;
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
        ParseClient::initialize(Config::APP_ID, Config::REST_KEY, Config::MASTER_KEY);
        ParseClient::setServerURL(Config::PARSE_URL,'parse');

        $this->s3Client = $this->prophesize('Aws\S3\S3Client');
        $this->mongoDbClient = $this->prophesize('MongoDB\Client');
        $this->pictureRepository = new PictureRepository($this->s3Client->reveal(), $this->mongoDbClient->reveal());

        $image = ParseFile::_createFromServer(
            'd6a07886620d4ee58df9a824a34af8bephoto_profile.jpg',
            'https://sofresh-bucket-recette.s3.amazonaws.com/pictures/d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg'
        );

        $this->picture =  $this->prophesize('Parse\ParseObject');
        $this->picture->get('image')->willReturn($image);
    }

    public function testRenamePictureReturnAnUpdateResult()
    {
        $collection = $this->prophesize('MongoDB\Collection');

//        $picture = ParseObject::create('Photos');
//        $picture->_mergeAfterFetch(['image' => $image]);

        $updateResult = $this->prophesize('MongoDB\UpdateResult');
        $updateResult->getMatchedCount()->willReturn(1);

        $collection->updateOne(Argument::type('array'), Argument::type('array'))->willReturn($updateResult);
        $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PICTURES_TABLE_NAME)->willReturn($collection);

        $actualUpdateResult = $this->pictureRepository->renamePicture($this->picture->reveal());

        $this->assertSame($updateResult->reveal(), $actualUpdateResult);
    }

    /**
     * @expectedException \Exception
     */
    public function testRenamePictureThrowAnExceptionWhenNoDocumentMatch()
    {
        $collection = $this->prophesize('MongoDB\Collection');

        $updateResult = $this->prophesize('MongoDB\UpdateResult');
        $updateResult->getMatchedCount()->willReturn(0);

        $collection->updateOne(Argument::type('array'), Argument::type('array'))->willReturn($updateResult);
        $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PICTURES_TABLE_NAME)->willReturn($collection);

        $this->pictureRepository->renamePicture($this->picture->reveal());
    }

    public function testUploadPictureReturnAnArray()
    {
        $this->s3Client->putObject(Argument::type('array'))->willReturn(new Result());
        $result = $this->pictureRepository->uploadPicture($this->picture->reveal());

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

    /**
     * @expectedException \Exception
     */
    public function testMigrateAllPictures()
    {
        $collection = $this->prophesize('MongoDB\Collection');
        $this->mongoDbClient->selectCollection(Config::MONGO_DB_NAME, Config::PICTURES_TABLE_NAME)->willReturn($collection);

        $writeResult = $this->prophesize('MongoDB\WriteResult');
        $insertResult = new InsertManyResult($writeResult->reveal(), array());

        $collection->insertMany(Argument::any())->willReturn($insertResult);
    }
}
