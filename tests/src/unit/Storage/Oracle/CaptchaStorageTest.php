<?php

namespace FRUnitTest\Captcha\Storage\Oracle;

use PHPUnit\Framework\TestCase;
use FR\Captcha\Storage\Oracle\CaptchaStorage;

class CaptchaStorageTest extends TestCase
{
    protected static $OracleTestAsset;
    protected static $DB;
    protected static $captcha_table_name;
    protected static $CaptchaStorage;

    /**
     * This method is executed only once per class
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$OracleTestAsset = new OracleTestAsset();

        self::$DB = self::$OracleTestAsset->getDB();
        self::$captcha_table_name = self::$OracleTestAsset->getCaptchaTableName();
        self::$CaptchaStorage = self::$OracleTestAsset->getCaptchaStorage();
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::__construct
     * 
     * @return void
     */
    public function constructor()
    {
        $invalid_captcha_table_name = ['', 123, true, []];
        foreach ($invalid_captcha_table_name as $i => $captcha_table_name) {
            $exception = false;
            try {
                new CaptchaStorage(self::$DB, $captcha_table_name);
            } catch (\Exception $expected) {
                $exception = true;
            }
            $this->assertTrue($exception, 'Exception not thrown: `captcha_table_name` cannot be empty and must be string');
        }
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::createDBStructure
     * 
     * @return void
     */
    public function createDBStructure()
    {
        self::$CaptchaStorage->createDBStructure();

        $query = ' SELECT UPPER(TABLE_NAME) TABLE_NAME FROM ALL_TABLES 
                    WHERE UPPER(OWNER || \'.\' || TABLE_NAME)
                                IN (:captcha_table_name)  ';
        $values = [
            ':captcha_table_name' => str_replace('"', '', strtoupper(self::$captcha_table_name)),
        ];
        $rows = self::$DB->fetchRows($query, $values);
        $this->assertNotEmpty($rows);
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::insertCaptcha
     * 
     * @return array
     */
    public function insertCaptcha()
    {
        $test = [
            [
                'captcha_id' => generateUniqueId(16),
                'captcha_expired_at' => '',
                'captcha_created_at' => ''
            ],
            [
                'captcha_id' => generateUniqueId(32),
                'captcha_expired_at' => date('Y-m-d H:i:s', strtotime('+ 1 Hour')),
                'captcha_created_at' => date('Y-m-d H:i:s')
            ],
            [
                'captcha_id' => generateUniqueId(32),
                'captcha_expired_at' => date('Y-m-d H:i:s', strtotime('- 1 Second')),
                'captcha_created_at' => date('Y-m-d H:i:s')
            ],
            [
                'captcha_id' => generateUniqueId(32),
                'captcha_expired_at' => date('Y-m-d H:i:s', strtotime('- 1 Minute')),
                'captcha_created_at' => date('Y-m-d H:i:s')
            ],
        ];
        $inserted = [];

        foreach ($test as $i => $param) {
            $param['captcha_image'] = strtolower($param['captcha_id'] . '.png');
            $param['captcha_code'] = rand(111111, 999999);

            if (in_array($i, [0])) {
                $exception = false;
                try {
                    invokeMethod(
                        self::$CaptchaStorage,
                        'insertCaptcha',
                        [
                            $param['captcha_id'],
                            $param['captcha_image'],
                            $param['captcha_code'],
                            $param['captcha_expired_at'],
                            $param['captcha_created_at'],
                        ]
                    );
                } catch (\Exception $expected) {
                    $exception = true;
                }
                $this->assertTrue($exception, 'Exception not thrown: `captcha_id` must be equal to 32 char');
            }

            // Insert into database
            if (in_array($i, [1, 2, 3])) {
                invokeMethod(
                    self::$CaptchaStorage,
                    'insertCaptcha',
                    [
                        $param['captcha_id'],
                        $param['captcha_image'],
                        $param['captcha_code'],
                        $param['captcha_expired_at'],
                        $param['captcha_created_at'],
                    ]
                );
                $this->assertTrue(true);

                $inserted[] = $param;
            }
        }

        return $inserted;
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::getCaptchaById
     * @depends insertCaptcha
     * 
     * @return void
     */
    public function getCaptchaById($test)
    {
        foreach ($test as $i => $param) {
            $row = invokeMethod(
                self::$CaptchaStorage,
                'getCaptchaById',
                [
                    $param['captcha_id'],
                ]
            );
            $this->assertArrayHasKey('captcha_id', $row);
            $this->assertArrayHasKey('captcha_image', $row);
            $this->assertArrayHasKey('captcha_code', $row);
            $this->assertArrayHasKey('captcha_expired_at', $row);
            $this->assertArrayHasKey('captcha_created_at', $row);

            $this->assertEqualsIgnoringCase($param['captcha_id'], $row['captcha_id']);
            $this->assertEquals($param['captcha_image'], $row['captcha_image']);
            $this->assertEquals($param['captcha_code'], $row['captcha_code']);
            $this->assertEquals($param['captcha_expired_at'], $row['captcha_expired_at']);
            $this->assertEquals($param['captcha_created_at'], $row['captcha_created_at']);
        }
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::deleteCaptchaById
     * @depends insertCaptcha
     * 
     * @return void
     */
    public function deleteCaptchaById($test)
    {
        foreach ($test as $i => $param) {
            invokeMethod(
                self::$CaptchaStorage,
                'deleteCaptchaById',
                [
                    $param['captcha_id'],
                ]
            );

            $row = invokeMethod(
                self::$CaptchaStorage,
                'getCaptchaById',
                [
                    $param['captcha_id'],
                ]
            );
            $this->assertIsArray($row);
            $this->assertEmpty($row);
        }
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::getExpiredCaptchas
     * 
     * @return void
     */
    public function getExpiredCaptchas()
    {
        // Create expired captcha
        $captcha_id = generateUniqueId(32);
        invokeMethod(
            self::$CaptchaStorage,
            'insertCaptcha',
            [
                $captcha_id,
                strtolower($captcha_id . '.png'),
                rand(111111, 999999),
                date('Y-m-d H:i:s', strtotime('-1 Second')),
                date('Y-m-d H:i:s'),
            ]
        );

        // Test
        $rows = invokeMethod(
            self::$CaptchaStorage,
            'getExpiredCaptchas',
            []
        );
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $row = $rows[0];

        $this->assertArrayHasKey('captcha_id', $row);
        $this->assertArrayHasKey('captcha_image', $row);
        $this->assertArrayHasKey('captcha_code', $row);
        $this->assertArrayHasKey('captcha_expired_at', $row);
        $this->assertArrayHasKey('captcha_created_at', $row);
        // Captcha must be expired
        $this->assertTrue((time() > strtotime($row['captcha_expired_at'])));
    }

    /**
     * @test
     * @covers FR\Captcha\Storage\Oracle\CaptchaStorage::deleteExpiredCaptchas
     * 
     * @return void
     */
    public function deleteExpiredCaptchas()
    {
        invokeMethod(
            self::$CaptchaStorage,
            'deleteExpiredCaptchas',
            []
        );

        $rows = invokeMethod(
            self::$CaptchaStorage,
            'getExpiredCaptchas',
            []
        );

        $this->assertIsArray($rows);
        $this->assertEmpty($rows);
    }
}
