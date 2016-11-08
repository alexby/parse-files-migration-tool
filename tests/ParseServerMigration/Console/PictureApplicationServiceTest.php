<?php

namespace ParseServerMigration\Console;

use Parse\ParseFile;
use Parse\ParseObject;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class PictureApplicationServiceTest extends \PHPUnit_Framework_TestCase
{
    /** @var PictureRepository | ObjectProphecy */
    private $pictureRepository;

    /** @var PictureApplicationService */
    private $pictureApplicationService;

    /** @var  ParseObject | ObjectProphecy*/
    private $picture;

    public function setUp()
    {
        $this->pictureRepository = $this->prophesize('ParseServerMigration\Console\PictureRepository');
        $this->pictureApplicationService = new PictureApplicationService($this->pictureRepository->reveal());

        $image = ParseFile::_createFromServer(
            'tfss-d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg',
            'https://sofresh-bucket-recette.s3.amazonaws.com/pictures/d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg'
        );
        $this->picture =  $this->prophesize('Parse\ParseObject');
        $this->picture->get(Argument::type('string'))->willReturn($image);
    }

    public function testMigrateImageReturnAStringWithOriginalFileNameAndUploadURL()
    {
        $updateResult = $this->prophesize('MongoDB\UpdateResult');
        $updateResult->getMatchedCount()->willReturn(1);

        $this->pictureRepository->renameImage($this->picture)->willReturn($updateResult);
        $this->pictureRepository->uploadImage($this->picture)->willReturn(['ObjectURL' => 'http://google.fr']);
        $result = $this->pictureApplicationService->migrateImage($this->picture->reveal());
        $this->assertRegExp('/.*tfss-d6a07886620d4ee58df9a824a34af8bephoto_profile.jpg.*google.fr/', $result);
    }

    public function testMigrateImageDoesNotUploadImageIfNoUpdateAreMade()
    {
        $updateResult = $this->prophesize('MongoDB\UpdateResult');
        $updateResult->getMatchedCount()->willReturn(0);

        $this->pictureRepository->renameImage($this->picture)->willReturn($updateResult);
        $result = $this->pictureApplicationService->migrateImage($this->picture->reveal());
        $this->assertRegExp('/Image : \[.*\] already migrated continuing/', $result);
    }

    //It should migrate all pictures !
    public function migrateAllPictures()
    {
        $insertResult = $this->pictureRepository->migrateAllPictures();

        if ($insertResult->getInsertedCount()) {
            throw new \Exception('An error occurred when inserting document into mongoDB');
        }

        return $insertResult;
    }
}
